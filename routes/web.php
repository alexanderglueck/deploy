<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'PageController@index')->name('page.index');

Auth::routes();

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', 'HomeController@index')->name('home');

    Route::get('/teams/{team}', 'TeamController@show')->name('team.show');

    Route::get('/teams/{team}/servers', 'ServerController@index')->name('server.index');
    Route::get('/teams/{team}/servers/create', 'ServerController@create')->name('server.create');
    Route::post('/teams/{team}/servers', 'ServerController@store')->name('server.store');
    Route::get('/teams/{team}/servers/{server}', 'ServerController@show')->name('server.show');
    Route::post('/teams/{team}/servers/{server}/setup', 'ServerSetupController@store')->name('server.setup.store');

    Route::get('/teams/{team}/projects/create', 'ProjectController@create')->name('project.create');
    Route::post('/teams/{team}/projects', 'ProjectController@store')->name('project.store');
    Route::get('/teams/{team}/projects/{project}', 'ProjectController@show')->name('project.show');

    Route::get('/teams/{team}/projects/{project}/workflows/create', 'WorkflowController@create')->name('workflow.create');
    Route::post('/teams/{team}/projects/{project}/workflows', 'WorkflowController@store')->name('workflow.store');
    Route::get('/teams/{team}/projects/{project}/workflows/{workflow}', 'WorkflowController@show')->name('workflow.show');
});
