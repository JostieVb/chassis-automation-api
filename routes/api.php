<?php

/*
|--------------------------------------------------------------------------
| Protected routes
|--------------------------------------------------------------------------
|
| These are the API routes that are protected by Laravel Passport.
| Some of these routes are role-based, so a middleware is applied to grant
| access to the right user.
|
*/

Route::group(['middleware' => 'auth:api'], function () {

    Route::group(['middleware' => 'permission:processes'], function () {
        Route::get('/processes', '\App\Processes\Http\Controllers\ProcessesController@getProcesses');
        Route::post('/processes/new', '\App\Processes\Http\Controllers\ProcessesController@newProcess');
        Route::post('/processes/save', '\App\Processes\Http\Controllers\ProcessesController@saveProcess');
        Route::get('/delete-process/{id}', '\App\Processes\Http\Controllers\ProcessesController@deleteProcess');
        Route::get('/deploy/{id}', '\App\Processes\Http\Controllers\ProcessesController@deployProcess');
        Route::get('/undeploy/{id}', '\App\Processes\Http\Controllers\ProcessesController@undeployProcess');
        Route::get('/get-process/{id}', '\App\Processes\Http\Controllers\ProcessesController@getProcess');
    });

    Route::group(['middleware' => 'permission:products'], function () {
        Route::get('/products', '\App\Products\Http\Controllers\ProductsController@getProducts');
    });

    Route::group(['middleware' => 'permission:entries'], function () {
        Route::get('/entries', '\App\Entries\Http\Controllers\EntriesController@getEntries');
        Route::get('/get-entries-by-status/{column?}', '\App\Entries\Http\Controllers\EntriesController@getEntriesByStatus');
        Route::get('/get-entries-numbers-by-status', '\App\Entries\Http\Controllers\EntriesController@getEntriesNumbersByStatus');
        Route::get('/count-unread-entries', '\App\Entries\Http\Controllers\EntriesController@countUnreadEntries');
        Route::get('/set-unread-false/{id}', '\App\Entries\Http\Controllers\EntriesController@setUnreadFalse');
        Route::post('/delete-entry', '\App\Entries\Http\Controllers\EntriesController@deleteEntry');
        Route::get('/entries/{id}', '\App\Entries\Http\Controllers\EntriesController@getEntry');
        Route::get('/entries/filter/{filter}', '\App\Entries\Http\Controllers\EntriesController@getEntriesByFilter');
        Route::get('/entries-get-process/{id}', '\App\Entries\Http\Controllers\EntriesController@getProcess');
    });

    Route::group(['middleware' => 'permission:forms'], function () {
        Route::post('/forms/form', '\App\Forms\Http\Controllers\FormsController@postForm');
        Route::get('/forms/get-forms', '\App\Forms\Http\Controllers\FormsController@getForms');
        Route::get('/forms/get-form/{id}', '\App\Forms\Http\Controllers\FormsController@getForm');
        Route::get('/forms/get-form-ids', '\App\Forms\Http\Controllers\FormsController@getFormIds');
    });

    Route::group(['middleware' => 'permission:data-tables'], function () {
        Route::get('/data/get-tables', '\App\DataTables\Http\Controllers\DataTablesController@getTables');
        Route::get('/data/get-columns/{table}', '\App\DataTables\Http\Controllers\DataTablesController@getColumns');
        Route::get('/data/delete-table/{table}', '\App\DataTables\Http\Controllers\DataTablesController@deleteTable');
        Route::post('/data/delete-column', '\App\DataTables\Http\Controllers\DataTablesController@deleteColumn');
        Route::post('/data/add-column', '\App\DataTables\Http\Controllers\DataTablesController@addColumn');
        Route::get('/data/get-detailed-columns/{table}', '\App\DataTables\Http\Controllers\DataTablesController@getDetailedColumns');
        Route::post('/data/create-data-table', '\App\DataTables\Http\Controllers\DataTablesController@createDataTable');
    });

    Route::post('/post/{form}', '\App\Bpmn\Http\Controllers\BpmnController@postForm');
    Route::get('/form/{form}', '\App\Bpmn\Http\Controllers\BpmnController@getForm');
    Route::post('/call', '\App\Bpmn\Http\Controllers\BpmnController@callHandler');
    Route::get('/get-data/{table}', '\App\Bpmn\Http\Controllers\BpmnController@getData');

    Route::get('/user', '\App\Auth\Http\Controllers\UserController@getAuthUser');
    Route::get('/users/{expect_current_user?}', '\App\Auth\Http\Controllers\UserController@getUsers');
    Route::get('/users', '\App\Auth\Http\Controllers\UserController@getUsers');
    Route::get('/user-permissions', '\App\Auth\Http\Controllers\UserController@getUserPermissions');
});

/*
|--------------------------------------------------------------------------
| Other routes
|--------------------------------------------------------------------------
|
| These are the API routes that are accessible without user authentication.
|
*/
    Route::post('/user/signup', '\App\Auth\Http\Controllers\UserController@signUp');
    Route::get('truncate', '\App\DataTables\Http\Controllers\DataTablesController@truncate');
