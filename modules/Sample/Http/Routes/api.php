<?php

/**
 * This is publicly accessible
 */
Route::group(['middleware' => []], function () {
    Route::get('/', 'SampleController@index');
});

/*
 * This is required to have a valid API key
 */
Route::group(['middleware' => [
    'api.auth',
]], function () {
    Route::get('/hello', 'SampleController@hello');
});
