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
Route::get('/api/test', 'ApiController@test');
Route::get('/api/v1/storm_reports', 'ApiController@stormReports');
Route::get('/api/v1/tornado_warning', 'ApiController@tornadoWarning');
Route::get('/api/v1/cimms_hail_probability', 'ApiController@cimmsHailProbability');
Route::get('/api/v1/cimms_tornado_probability', 'ApiController@cimmsTornadoProbability');
Route::get('/api/v1/cimms_wind_probability', 'ApiController@cimmsWindProbability');
Route::get('/api/v1/stats', function(){
    return[
        'series' => 200,
        'lessons' => 1300
    ];
});