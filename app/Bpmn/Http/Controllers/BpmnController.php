<?php

namespace App\Bpmn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Bpmn\Models\Link;
use App\Processes\Models\Process;
use App\Forms\Models\Form;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Entries\Models\Entry;

class BpmnController extends Controller
{
    private $processId;
    private $workflow;

    /**
     * Handle caller request
     *
     * @param   request  $request
     * @return  string
     */
    protected function callHandler(Request $request) {
        $callerId = $request['caller'];
        $dbTable = $request['dbTable'];
        $insertId = $request['insertId'];
        if ($this->getWorkflow($callerId) !== false) {
            $this->workflow = $this->getWorkflow($callerId);
            $properties = Process::where('id', $this->processId)->pluck('properties')->first();
            $properties = json_decode($properties, true);
            if ($request->has('current_task')) {
                $data = $request['data'];
                $currentTaskId = $request['current_task'];
            } else {
                $currentTaskId = $this->getCurrentId($properties, $callerId);
            }
            $nextTaskId = $this->getNextId($this->workflow, $currentTaskId);
            $nextTaskType = $this->getType($nextTaskId);
            if ($nextTaskType == 'endevent') {
                return response()->json(['message' => 'End event successfully reached', 'status' => 200], 200);
            } else if ($nextTaskType == 'exclusivegateway') {
                return $this->executeGateway($request['entry_id'], $properties, $nextTaskId, $dbTable, $insertId, $data, $callerId);
            } else {
                return $this->executeTask($properties, $nextTaskId, $dbTable, $insertId, $callerId);
            }
        }
        return response()->json(['message' => 'No workflow found for caller \'' . $callerId . '\'.', 'status' => 404], 404);
    }

    /**
     * Get the caller's workflow
     *
     * @param   string  $caller
     * @return  string
     */
    private function getWorkflow($caller) {
        $linkedProcess = Link::select('process_id')->where('caller_name', $caller)->first();
        if ($linkedProcess) {
            $processId = $linkedProcess['process_id'];
            $process = Process::where([
                ['id', $processId],
                ['deploy', 'true'],
                ['deleted', 'false']
            ])->get();
            if ($process->count() > 0) {
                $this->processId = $processId;
                return json_decode($process[0]['process_json'], true)['bpmn:process'];
            }
            return false;
        }
        return false;
    }

    /**
     * Get id of current task
     *
     * @param   mixed   $properties
     * @param   string    $caller
     * @return  string
     */
    private function getCurrentId($properties, $caller) {
        foreach($properties as $id => $property) {
            if ($property['caller'] == $caller) {
                return $id;
            }
        }
        return null;
    }

    /**
     * Get id of next task
     *
     * @param   mixed     $workflow
     * @param   string    $currentTaskId
     * @return  string
     */
    private function getNextId($workflow, $currentTaskId) {
        $sequences = $workflow['bpmn:sequenceFlow'];
        foreach ($sequences as $sequence) {
            if ($sequence['@sourceRef'] == $currentTaskId) {
                return $sequence['@targetRef'];
            }
        }
    }

    /**
     * Execute the task and build an entry
     *
     * @param   mixed      $properties
     * @param   string     $task
     * @param   string     $dbTable
     * @param   integer    $insertId
     * @param   string     $callerId
     * @param   string     $responseMessage
     * @return  mixed
     */
    private function executeTask($properties, $task, $dbTable, $insertId, $callerId, $responseMessage = null) {
        if (array_key_exists($task, $properties)) {
            $assignees = $properties[$task]['assignees'];
            foreach ($assignees as $assignee) {
                if ($assignee == 'initiator') {
                    $assignee = DB::table($dbTable)->where('id', $insertId)->pluck('added_by')->first();
                }
                $entry = new Entry();
                if (count($properties[$task]['decisions']) == 0) {
                    $entry->status = 'notification';
                    $entry->label = 'Notification';
                }
                if ($responseMessage) {
                    $entry->response_message = $responseMessage;
                }
                $entry->sender_id = Auth::id();
                if (Auth::id() == $assignee) {
                    $entry->sender_id = 0;
                }
                $entry->recipient_id = $assignee;
                $entry->process_id = $this->processId;
                $entry->db_table = $dbTable;
                $entry->task_id = $task;
                $entry->content_id = $insertId;
                $entry->process_id = $this->processId;
                $entry->caller = $callerId;
                $entry->save();
                $nextTaskId = $this->getNextId($this->workflow, $task);
                $type = $this->getType($nextTaskId);
                if ($type == 'exclusivegateway' && $type != 'endevent') {
                    return response()->json(['message' => 'Exclusive gateway found.', 'status' => 200], 200);
                }
                if ($type == 'endevent') {
                    return response()->json(['message' => 'Successfully reached end event. 1', 'status' => 200], 200);
                }
                $this->executeTask($properties, $nextTaskId, $dbTable, $insertId, $callerId);
            }
        }
        return response()->json(['message' => 'Waiting for exclusive gateway on current task \''.$task.'\'', 'status' => 200], 200);
    }

    /**
     * Execute the gateway by finding the task corresponding to the decision
     *
     * @param   integer     $entryId
     * @param   mixed       $properties
     * @param   string      $gatewayId
     * @param   string      $dbTable
     * @param   integer     $insertId
     * @param   mixed       $data
     * @param   string      $callerId
     * @return  mixed
     */
    private function executeGateway($entryId, $properties, $gatewayId, $dbTable, $insertId, $data, $callerId) {
        $decision = $data['decision'];
        $outputs = $properties[$gatewayId]['output'];
        foreach ($outputs as $output) {
            if ($output['value'] == $decision) {
                Entry::where('id', $entryId)->update(['status' => 'completed', 'label' => 'Completed']);
                DB::table($dbTable)->where('id', $insertId)->update(['status' => $decision]);
                return $this->executeTask($properties, $output['id'], $dbTable, $insertId, $callerId, $data['message']);
            }
        }
        return response()->json(['message' => 'No decision found', 'status' => 404], 404);
    }

    /**
     * Get type of item
     *
     * @param   string  @taskId
     * @return  string
     */
    private function getType($taskId) {
        return strtolower(substr($taskId, 0, strpos($taskId, '_')));
    }

    /**
     * Get form from database
     *
     * @param   string  $form
     * @return  mixed
     */
    protected function getForm($form) {
        $form = Form::where([
            ['identifier', $form],
            ['deleted', 'false']
        ])->get();
        return response()->json($form, 200);
    }

    /**
     * Insert form data into database
     *
     * @param   string  $caller
     * @param   mixed   $request
     * @return  mixed
     */
    protected function postForm($caller, Request $request) {
        if ($request) {
            $form = Form::where('identifier', $caller)->get()[0];
            $timestamp = Carbon::now()->toDateTimeString();
            $query_data = ['added_by' => Auth::id(),'created_at' => $timestamp, 'updated_at' => $timestamp];
            foreach ($request['data'] as $data) {
                $query_data[$data['column']] = $data['value'];
            }

            if ($insert_id = DB::table($form['db_table'])->insertGetId($query_data)) {
                $return_query = 'SELECT * FROM '.$form['db_table'].' WHERE added_by='.Auth::id();
                $return_data = ['data' => DB::select(DB::raw($return_query)), 'insert_id' => $insert_id, 'db_table' => $form['db_table'], 'status' => 200];
                return response()->json($return_data, 200);
            }
            return response()->json(['message' => 'Could not add to database \''.$form['db_table'].'\'.', 'status' => 500], 500);
        }
        return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
    }

    /**
     * Get the inserted data by corresponding entry
     *
     * @param   string     $table
     * @return  mixed
     */
    protected function getData($table) {
        if ($table) {
            return DB::table($table)->where('added_by', Auth::id())->get();
        }
        return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
    }
}