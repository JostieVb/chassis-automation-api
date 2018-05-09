<?php

namespace App\Entries\Http\Controllers;

use App\Auth\Models\Users;
use App\Http\Controllers\Controller;
use App\Entries\Models\Entry;
use Illuminate\Support\Facades\Auth;
use App\Processes\Models\Process;
use Illuminate\Support\Facades\DB;
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
            $entry->title = $properties[$entry['task_id']]['title'];
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
        $dbTable = $entry['db_table'];
        $contentId = $entry['content_id'];
        $properties = Process::select('properties')->where('id', $processId)->pluck('properties')->first();
        $properties = json_decode($properties, true);
        $entry['title'] = $properties[$taskId]['title'];
        $entry['decisions'] = $properties[$taskId]['decisions'];
        if ($senderId == 0) {
            $entry['sender'] = 'System';
        } else {
            $entry['sender'] = Users::select('name')->where('id', $senderId)->pluck('name')->first();
        }
        $entry['recipient'] = Users::select('name')->where('id', $recipientId)->pluck('name')->first();
        $entry['content'] = [];
        if ($dbTable && $contentId) {
            $entry['content'] = $this->getEntryContent($dbTable, $contentId);
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
     * Get posted form content of corresponding entry
     *
     * @param   string      $dbTable
     * @param   number      $contentId
     * @return  mixed
     */
    private function getEntryContent($dbTable, $contentId) {
        $content = DB::table($dbTable)->where('id', $contentId)->get()[0];
        $keys = [];
        $values = [];
        foreach ($content as $key => $value) {
            if (substr($key, 0, 3) == 'ca_') {
                $newKey = substr($key, 3, strlen($key));
                array_push($keys, $newKey);
                array_push($values, $value);
            }
            if ($key == 'status') {
                array_push($keys, 'status');
                array_push($values, $value);
            }
        }
        $data = ['keys' => $keys, 'values' => $values];
        return $data;
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