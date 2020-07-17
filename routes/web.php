<?php

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

Route::get('/', function () {
    return [
        'Project' => 'Tornado Vision API',
        'version' => '1.0'
    ];
});
Route::get('/map', function () {
    return view('map');
});
//https://weathervision.app/api/v1/uid=(UID here),(lat),(lon)
Route::get('/api/v1', 'ApiController@getReports');
Route::get('/api/v1/test', 'TestApiController@getReports');
//Route::get('/api/v1/overlapping', 'TestApiController@overlapping');
Route::get('/overlapping', 'TestApiController@overlapping');
Route::get('/overlapping1', 'TestApiController@overlapping1');
//Route::get('/api/test', 'ApiController@test');