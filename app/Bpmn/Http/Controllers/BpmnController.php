<?php

namespace App\Bpmn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Processes\Models\Process;
use App\Forms\Models\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Entries\Models\Entry;
use App\Bpmn\Models\BpmnContent;

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
        $insertId = $request['insertId'];
        $this->workflow = $this->getWorkflow($callerId);
        if ($this->workflow !== false) {
            $properties = json_decode(Process::where('id', $this->processId)->pluck('properties')->first(), true);
            $data = null;
            if ($request->has('current_task')) {
                $data = $request['data'];
                $currentTaskId = $request['current_task'];
            } else {
                $currentTaskId = $this->getCurrentId($properties, $callerId);
            }
            $nextTaskId = $this->getNextId($this->workflow, $currentTaskId);
            if ($nextTaskId == null) {
                return response()->json(['message' => 'Could not find next task id.', 'status' => 404], 404);
            }
            $nextTaskType = $this->getType($nextTaskId);
            if ($nextTaskType == 'endevent') {
                return response()->json(['message' => 'End event successfully reached', 'status' => 200], 200);
            } else if ($nextTaskType == 'exclusivegateway') {
                return $this->executeGateway($properties, $nextTaskId, $insertId, $callerId, $data, $request['entry_id']);
            } else {
                return $this->executeTask($properties, $nextTaskId, $insertId, $callerId);
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
        $process = Process::where([
            ['caller', $caller],
            ['deploy', 'true'],
            ['deleted', 'false']
        ])->first();
        if ($process) {
            $this->processId = $process['id'];
            return json_decode($process['process_json'], true)['bpmn:process'];
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
        if (isset($workflow['bpmn:sequenceFlow'])) {
            $sequences = $workflow['bpmn:sequenceFlow'];
            foreach ($sequences as $sequence) {
                if ($sequence['@sourceRef'] == $currentTaskId) {
                    return $sequence['@targetRef'];
                }
            }
        }
        return null;
    }

    /**
     * Execute the task and build an entry
     *
     * @param   mixed      $properties
     * @param   string     $task
     * @param   integer    $insertId
     * @param   string     $callerId
     * @param   string      $message
     * @return  mixed
     */
    private function executeTask($properties, $task, $insertId, $callerId, $message = null) {
        if (array_key_exists($task, $properties)) {
            $assignees = $properties[$task]['assignees'];
            foreach ($assignees as $assignee) {
                $assignee = $assignee['value'];
                if ($assignee == 'initiator') {
                    $assignee = BpmnContent::where('id', $insertId)->pluck('added_by')->first();
                }
                $entry = new Entry();
                if (!array_key_exists('decisions', $properties[$task])) {
                    $entry->status = 'notification';
                    $entry->label = 'Notification';
                }
                if (!array_key_exists('message', $properties[$task])) {
                    $entry->response_message = $properties[$task]['message'];
                }
                $entry->sender_id = Auth::id();
                if (Auth::id() == $assignee) {
                    $entry->sender_id = 0;
                }
                if ($message != null) {
                    $entry->response_message = $message;
                }
                $entry->due = null;
                if (array_key_exists('due', $properties[$task])) {
                    $year = substr($properties[$task]['due'], 0, strpos($properties[$task]['due'], 'T'));
                    $time = substr($properties[$task]['due'], strpos($properties[$task]['due'], 'T'), strpos($properties[$task]['due'], '.'));
                    $date = date_create($year . $time);
                    $due = date_format($date,'Y/m/d H:i:s');
                    $entry->due = $due;
                }
                $entry->recipient_id = $assignee;
                $entry->task_id = $task;
                $entry->content_id = $insertId;
                $entry->process_id = $this->processId;
                $entry->caller = $callerId;
                $entry->save();
                $nextTaskId = $this->getNextId($this->workflow, $task);
                if ($nextTaskId == null) {
                    return response()->json(['message' => 'Could not find next task id.', 'status' => 404], 404);
                }
                $type = $this->getType($nextTaskId);
                if ($type == 'exclusivegateway' && $type != 'endevent') {
                    if (!isset($properties[$task]['decisions'])) {
                        $this->executeGateway($properties, $nextTaskId, $insertId, $callerId);
                    } else {
                        return response()->json(['message' => 'Exclusive gateway found.', 'status' => 200], 200);
                    }
                }
                if ($type == 'endevent') {
                    return response()->json(['message' => 'Successfully reached end event. 1', 'status' => 200], 200);
                }
                $this->executeTask($properties, $nextTaskId, $insertId, $callerId);
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
     * @param   integer     $contentId
     * @param   mixed       $data
     * @param   string      $callerId
     * @return  mixed
     */
    private function executeGateway($properties, $gatewayId, $contentId, $callerId, $data = null, $entryId = null) {
        $sequences = $this->getSequenceArrowsAfterGateway($gatewayId);
        if ($sequences != null) {
            $decision = '';
            if ($data != null) {
                $decision = $data['decision'];
            }
            foreach ($sequences as $sequence) {
                if (isset($properties[$sequence]) && isset($properties[$sequence]['conditions'])) {
                    $conditions = $properties[$sequence]['conditions'];
                    $follow = $properties[$sequence]['follow'];
                    $conditionsOutput = $this->validateConditions($conditions, $contentId, $decision);
                    if ($this->validateSequenceFlow($conditionsOutput, $follow)) {
                        if ($entryId != null) {
                            Entry::where('id', $entryId)->update(['status' => 'completed', 'label' => 'Completed']);
                        }
                        BpmnContent::where('id', $contentId)->update(['status' => $decision]);
                        $nextTaskId = $this->getTaskIdAfterGateway($sequence);
                        if ($nextTaskId != null) {
                            return $this->executeTask($properties, $nextTaskId, $contentId, $callerId, $data['message']);
                        } else {
                            return response()->json(['message' => 'Could not found next task after \''.$sequence.'\'', 'status' => 404], 404);
                        }
                    }
                } else {
                    return response()->json(['message' => 'No properties or conditions found for \''.$sequence.'\'', 'status' => 404], 404);
                }
            }
            return 'done';
        } else {
            return response()->json(['message' => 'No sequences after \''.$gatewayId.'\' found', 'status' => 404], 404);
        }
    }

    /**
     * Get all sequence flow arrows that occur after a gateway
     *
     * @param  string   $gatewayId
     * @return mixed
     */
    private function getSequenceArrowsAfterGateway($gatewayId) {
        $gateways = $this->workflow['bpmn:exclusiveGateway'];
        if (isset($gateways['@id'])) {
            if ($gateways['@id'] == $gatewayId) {
            return $gateways['bpmn:outgoing'];
            }
        } else {
            foreach ($gateways as $gateway) {
                if ($gateway['@id'] == $gatewayId) {
                    return $gateway['bpmn:outgoing'];
                }
            }
        }
        return null;
    }

    /**
     * Validate all conditions that are linked to the sequence
     * to decide which turn the workflow must take
     *
     * @param   array    $conditions
     * @param   integer  $contentId
     * @param   string   $decision
     * @return  mixed
     */
    private function validateConditions($conditions, $contentId, $decision) {
        $validations = [];
        foreach ($conditions as $id => $condition) {
            $key = $this->getValueForCondition($condition['key'], $contentId, $decision);
            $operator = $condition['operator'];
            $value = $condition['value']['value'];
            if ($operator == 'equals') {
                if ($key == $value) {
                    array_push($validations, true);
                } else {
                    array_push($validations, false);
                }
            } else {
                if ($key != $value) {
                    array_push($validations, true);
                } else {
                    array_push($validations, false);
                }
            }
        }
        return $validations;
    }

    /**
     * Check if the output of the conditions is sufficient enough
     * to follow the sequence that is currently being checked
     *
     * @param   mixed   $conditions
     * @param   string  $follow
     * @return  boolean
     */
    private function validateSequenceFlow($conditions, $follow) {
        if ($follow == 'all' && !in_array(false, $conditions)) {
            return true;
        } else {
            if (in_array(true, $conditions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the right starting value for the condition that should be checked
     *
     * @param   string  $key
     * @param   integer $contentId
     * @param   string  $decision
     * @return  mixed
     */
    private function getValueForCondition($key, $contentId, $decision) {
        if ($key == 'previous-decision') {
            // The value must be equal to the value of the previous decision
            return $decision;
        } else {
            // Key is value of form field
            $content = json_decode(BpmnContent::where('id', $contentId)->pluck('data')->first(), true);
            if (isset($content[$key])) {
                return $content[$key];
            }
        }
        return null;
    }

    private function getTaskIdAfterGateway($sequence) {
        $tasks = $this->workflow['bpmn:task'];
        if (isset($tasks['@id'])) {
            return $tasks['@id'];
        } else {
            foreach ($tasks as $task) {
                if ($task['bpmn:incoming'] == $sequence) {
                    return $task['@id'];
                }
            }
        }
        return null;
    }

    /**
     * Get type of item
     *
     * @param   string  $taskId
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
     * Get all input fields of a form
     *
     * @param   string  $form
     * @return  array
     */
    private function getFormInputFields($form) {
        $fields = Form::where('identifier', $form)->get()->first();
        $output = [];
        foreach ($fields as $id => $field) {
            if ($field['type'] != 'title' && $field['type'] != 'subtitle') {
                $output[$id] = $field['name'];
            }
        }
        return $output;
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
            $query_data = new BpmnContent();
            $query_data->added_by = Auth::id();
            $query_data->form = $caller;
            $data_array = [];

            foreach ($request['data'] as $data) {
                $data_array[$data['name']] = $data['value'];
            }

            $query_data->data = json_encode($data_array);
            $return_data = [];
            if ($query_data->save()) {
                $return_data['status'] = 200;
                $return_data['data'] = $this->getData($caller);
                $return_data['insert_id'] = $query_data->id;
                return response()->json($return_data, 200);
            }
            return response()->json(['message' => 'Could not add save the data.', 'status' => 500], 500);
        }
        return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
    }

    /**
     * Get the inserted data by corresponding entry
     *
     * @param   string     $form
     * @return  mixed
     */
    protected function getData($form) {
        if ($form) {
            $return['content'] = BpmnContent::where([
                ['form', '=', $form],
                ['added_by', '=', Auth::id()]
            ])->get();
            $structure = [];
            foreach (json_decode(Form::where('identifier', $form)->pluck('structure')->first(), true) as $id => $field) {
                if ($field['type'] != 'title' && $field['type'] != 'subtitle') {
                    $structure[$id] = $field;
                }
            }
            foreach ($return['content'] as $key => $content) {
                $data = json_decode($content['data'], true);
                $data['status'] = $content['status'];
                $return['content'][$key]['data'] = $data;
            }
            $return['structure'] = $structure;
            return $return;
        }
        return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
    }
}