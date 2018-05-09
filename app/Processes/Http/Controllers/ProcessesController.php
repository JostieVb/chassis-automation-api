<?php

namespace App\Processes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Processes\Models\Process;
use Illuminate\Http\Request;
use Nathanmac\Utilities\Parser\Facades\Parser;
use App\Bpmn\Models\Link;

class ProcessesController extends Controller
{
    /**
     * Get all processes
     *
     * @return mixed response
     */
    protected function getProcesses() {
        $processes = Process::where('deleted', 'false')->get();
        return response()->json($processes, 200);
    }

    /**
     * Get process by id
     *
     * @param   integer  $id
     * @return  mixed    response
     */
    protected function getProcess($id) {
        $process = Process::where([
            ['id', '=', $id],
            ['deleted', '=', 'false']
        ]);
        if ($process->count() > 0) {
            return response()->json($process->get(), 200);
        } else {
            return response()->json(['message', 'Something went wrong'], 500);
        }
    }

    /**
     * Add new process from request
     *
     * @param   mixed   $request
     * @return  mixed   response
     */
    protected function newProcess(Request $request) {
        if ($request) {
            $process = new Process();
            $process->code = $request['code'];
            $process->name = $request['name'];
            $process->process_xml = $request['process_xml'];
            $process->process_json = json_encode(Parser::xml($request['process_xml']));
            $process->properties = $request['properties'];
            $process->save();
            $this->linkProcessToCaller($request['callers'], $process->id);
            return response()->json(['message' => 'Successfully saved', 'id' => $process->id, 'status' => 200], 200);
        }
        return response()->json(['message' => 'Could not create new process', 'status' => 500], 500);
    }

    /**
     * Save process by saving from request
     *
     * @param   mixed   $request
     * @return  mixed   response
     */
    protected function saveProcess(Request $request) {
        if ($request) {
            Process::where('id', $request['id'])->update([
                'name' => $request['name'],
                'code' => $request['code'],
                'process_xml' => $request['process_xml'],
                'process_json' => json_encode(Parser::xml($request['process_xml'])),
                'properties' => $request['properties']
            ]);
            $this->linkProcessToCaller($request['callers'], $request['id']);

            return response()->json(['message' => 'Successfully saved', 'status' => 200], 200);
        }
        return response()->json(['message' => 'Could not save process', 'status' => 500], 500);
    }

    private function linkProcessToCaller($callers, $id) {
        foreach($callers as $caller) {
            $check = Link::where([['caller_name', $caller], ['process_id', $id]])->count();
            if ($check == 0) {
                $link = new Link();
                $link->caller_name = $caller;
                $link->process_id = $id;
                $link->save();
            }
        }
    }

    /**
     * Delete process by id
     *
     * @param   integer   $id
     * @return  mixed     response
     */
    protected function deleteProcess($id) {
        if (Process::where('id', $id)->update(['deleted' => 'true'])) {
            return response()->json(['message' => 'Successfully deleted', 'status' => 200], 200);
        } else {
            return response()->json(['message' => 'Something went wrong', 'status' => 200], 500);
        }
    }

    /**
     * Deploy process by id
     *
     * @param   integer   $id
     * @return  mixed     response
     */
    protected function deployProcess($id) {
        if (Process::where('id', $id)->update(['deploy' => 'true'])) {
            return response()->json(['message' => 'Successfully deployed', 'status' => 200], 200);
        } else {
            return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
        }
    }

    /**
     * Undeploy process by id
     *
     * @param   integer   $id
     * @return  mixed     response
     */
    protected function undeployProcess($id) {
        if (Process::where('id', $id)->update(['deploy' => 'false'])) {
            return response()->json(['message' => 'Successfully undeployed', 'status' => 200], 200);
        } else {
            return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
        }
    }
}