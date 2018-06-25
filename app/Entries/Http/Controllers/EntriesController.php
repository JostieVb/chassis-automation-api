<?php

namespace App\Entries\Http\Controllers;

use App\Auth\Models\Users;
use App\Bpmn\Models\BpmnContent;
use App\Forms\Models\Form;
use App\Http\Controllers\Controller;
use App\Entries\Models\Entry;
use Illuminate\Support\Facades\Auth;
use App\Processes\Models\Process;
use Illuminate\Http\Request;

class EntriesController extends Controller
{
    /**
     * Get the entries
     *
     * @return  mixed
     */
    protected function getEntries() {
        $entries = Entry::where([
            ['recipient_id', Auth::user()->id],
            ['deleted', 'false']
        ])->orderBy('date', 'DESC')->get();
        $entries = $this->setEntriesTitles($entries);
        return response()->json($entries, 200);
    }

    /**
     * Get the entries by a filter
     *
     * @param   string      $filter
     * @return  mixed
     */
    protected function getEntriesByFilter($filter) {
        if($filter != 'all') {
            $entries = Entry::where([
                ['recipient_id', Auth::user()->id],
                ['status', $filter],
                ['deleted', 'false']
            ])->orderBy('date', 'DESC')->get();
            $entries = $this->setEntriesTitles($entries);
            return response()->json($entries, 200);
        }
        return $this->getEntries();
    }

    /**
     * Set titles of the given entries
     *
     * @param   mixed      $entries
     * @return  mixed
     */
    protected function setEntriesTitles($entries) {
        foreach ($entries as $entry) {
            $properties = Process::select('properties')->where('id', $entry['process_id'])->pluck('properties')->first();
            $properties = json_decode($properties, true);
            $entry->title = '[No subject]';
            if (isset($properties[$entry['task_id']]['subject'])) {
                $entry->title = $properties[$entry['task_id']]['subject'];
            }
        }
        return $entries;
    }

    /**
     * Get entry by id and format data
     *
     * @param   integer      $id
     * @return  mixed
     */
    protected function getEntry($id) {
        $entry = Entry::where([
            ['id', $id],
            ['deleted', 'false'],
            ['recipient_id', Auth::user()->id]
        ])->first();
        $processId = $entry['process_id'];
        $taskId = $entry['task_id'];
        $recipientId = $entry['recipient_id'];
        $senderId = $entry['sender_id'];
        $form = $entry['caller'];
        $contentId = $entry['content_id'];
        $properties = Process::select('properties')->where('id', $processId)->pluck('properties')->first();
        $properties = json_decode($properties, true);
        $entry['title'] = '[No subject]';
        if (isset($properties[$taskId]['subject'])) {
            $entry['title'] = $properties[$taskId]['subject'];
        }
        $entry['decisions'] = [];
        if (isset($properties[$taskId]['decisions'])) {
            $entry['decisions'] = $properties[$taskId]['decisions'];
        }
        if ($senderId == 0) {
            $entry['sender'] = 'System';
        } else {
            $entry['sender'] = Users::select('name')->where('id', $senderId)->pluck('name')->first();
        }
        $entry['recipient'] = Users::select('name')->where('id', $recipientId)->pluck('name')->first();
        $entry['attached-contents'] = [];
        if ($contentId && $properties[$taskId]['attach-form-contents']) {
            $array = [];
            array_push($array, array('type' => 'form-contents', 'id' => $contentId, 'title' => 'Attached content'));
            $entry['attached-contents'] = $array;
        }
        $entry['message'] = $this->formatText($properties[$taskId]['message'], $entry);
        Entry::where('id', $id)->update(['unread' => 'false']);
        return $entry;
    }

    /**
     * Delete an entry
     *
     * @param   request     $request
     */
    protected function deleteEntry(Request $request) {
        if ($request['entry_id']) {
            Entry::where('id', $request['entry_id'])->update(['deleted' => 'true']);
        }
    }

    /**
     * Count unread entries
     *
     * @return  number
     */
    protected function countUnreadEntries() {
        return Entry::where(['recipient_id' => Auth::id(), 'unread' => 'true'])->count();
    }

    /**
     * Set unread to false by entry id
     *
     * @param   number  $id
     * @return  mixed
     */
    protected function setUnreadFalse($id) {
        return Entry::where('id', $id)->update(['unread' => 'false']);
    }

    /**
     * Get attachment of type form contents
     *
     * @param   string      $form
     * @param   number      $id
     * @return  mixed
     */
    protected function getFormContentsAttachment($contentId, $form) {
        $form = json_decode(Form::where('identifier', $form)->pluck('structure')->first(), true);
        $fields = [];
        foreach ($form as $id => $field) {
            if ($field['type'] !== 'title' && $field['type'] !== 'subtitle') {
                $fields[$id] = $field['name'];
            }
        }
        $content = json_decode(BpmnContent::where('id', $contentId)->pluck('data')->first(), true);
        return array('keys' => $fields, 'values' => $content);
    }

    /**
     * Replace variable from text with their values
     *
     * @param   string      $text
     * @param   mixed       $entry
     * @return  string
     */
    private function formatText($text, $entry) {
        if (strpos($text, '[name]')) {
            $text = str_replace('[name]', $entry['sender'], $text);
        }
        return $text;
    }

    /**
     * Get entries by status
     *
     * @param   string      $column (optional)
     * @return  mixed
     */
    protected function getEntriesByStatus($column = null) {
        $entries = Entry::get();
        $status_array = array();
        $result = json_decode('[]');

        foreach ($entries as $entry) {
            if(!in_array($entry['status'], $status_array)) {
                array_push($status_array, $entry['status']);
            }
        }

        foreach($status_array as $status) {
            if($column != null) {
                $query_result = Entry::where('status', $status)->pluck($column);
            } else {
                $query_result = Entry::where('status', $status)->get();
            }
            $result[$status] = $query_result;
        }

        return response()->json(json_encode($result), 200);
    }

    /**
     * Get number of entries by status
     *
     * @return  mixed
     */
    protected function getEntriesNumbersByStatus() {
        $entries = Entry::get();
        $status_array = array();
        $result = json_decode('[]');

        foreach ($entries as $entry) {
            if(!in_array($entry['status'], $status_array)) {
                array_push($status_array, $entry['status']);
            }
        }

        foreach($status_array as $status) {
            $query_result = Entry::where('status', $status)->count();
            $result[$status] = $query_result;
        }

        return response()->json(json_encode($result), 200);
    }

    /**
     * Get process properties and json structure by id
     *
     * @return  mixed
     */
    protected function getProcess($id) {
        $process = Process::select('properties', 'process_json')->where([
            ['id', '=', $id],
            ['deleted', '=', 'false']
        ]);
        if ($process->count() > 0) {
            return response()->json($process->get(), 200);
        } else {
            return response()->json(['message', 'Something went wrong'], 500);
        }
    }
}