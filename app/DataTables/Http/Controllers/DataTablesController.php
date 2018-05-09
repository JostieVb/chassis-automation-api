<?php

namespace App\DataTables\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DataTablesController extends Controller
{
    /**
     * Get all database tables
     *
     * @return  mixed
     */
    protected function getTables() {
        $tables = DB::select('SHOW TABLES');
        $linkableTables = [];
        foreach ($tables as $table) {
            $name = $table->Tables_in_chassis_automation;
            if (preg_match('(ca_)', $name) === 1) {
                array_push($linkableTables, ['name' => $name]);
            }
        }
        return response()->json($linkableTables, 200);
    }

    /**
     * Delete database table
     *
     * @param   string  $table
     * @return  mixed
     */
    protected function deleteTable($table) {
        return Schema::dropIfExists($table);
    }

    /**
     * Get all columns of database table
     *
     * @param   string  $table
     * @return  mixed
     */
    protected function getColumns($table) {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        $linkableColumns = [];
        foreach ($columns as $column) {
            if (preg_match('(ca_)', $column) === 1) {
                array_push($linkableColumns, ['name' => $column]);
            }
        }
        return response()->json($linkableColumns, 200);
    }

    /**
     * Add new column to table
     *
     * @param   request     $request
     * @return  mixed
     */
    protected function addColumn(Request $request) {
        if ($request) {
            $table = $request['table'];
            $column = $request['column'];
            $query = 'ALTER TABLE '.$table.' ADD '.$column['name'].' '.$column['type'];
            if ($request['type'] == 'VARCHAR') {
                $query .= '(255)';
            }
            DB::select(DB::raw($query));
            return response()->json(['message' => 'Data table column \'' . $column['name'] . '\' successfully added.', 'status' => 200], 200);
        }
        return response()->json(['message' => 'Could not add column.', 'status' => 500], 200);
    }

    protected function deleteColumn(Request $request) {
        return DB::select(DB::raw('ALTER TABLE '.$request['table'].' DROP COLUMN '.$request['column']));
    }

    /**
     * Create new data table
     *
     * @param   request     $request
     * @return  mixed
     */
    protected function createDataTable(Request $request) {
        if ($request) {
            if (Schema::hasTable($request['table_name'])) {
                return response()->json(['message' => 'Data table \'' . $request['table_name'] . '\' already exists.', 'status' => 500], 200);
            }
            Schema::create($request['table_name'], function($table) {
                $table->increments('id');
                $table->string('added_by');
                $table->string('status');
                $table->timestamps();
            });
            return response()->json(['message' => 'Data table  \'' . $request['table_name'] . '\' was successfully created.', 'status' => 200], 200);
        }
        return response()->json(['message' => 'Could not create \'' . $request['table_name'] . '\' data table.', 'status' => 500], 200);
    }

    /**
     * Get detailed overview from data table columns
     *
     * @param   string      $table
     * @return  mixed
     */
    protected function getDetailedColumns($table) {
        $columns = Db::select(DB::raw('SHOW COLUMNS FROM '.$table));
        $ca_columns = [];
        foreach ($columns as $column) {
            if (substr($column->Field, 0, 3) == 'ca_') {
                array_push($ca_columns, $column);
            }
        }
        return $ca_columns;
    }

    /**
     * Dev function to truncate data tables
     *
     */
    protected function truncate() {
        $dataTables = ['ca_product', 'ca_customer', 'entry'];
        foreach ($dataTables as $dataTable) {
            DB::table($dataTable)->truncate();
        }
        return response()->json(['message' => 'Succesfully truncated data tables \'' . implode(', ', $dataTables) . '\'.'], 200);
    }
}