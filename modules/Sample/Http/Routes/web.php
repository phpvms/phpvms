<?php

Route::group(['middleware' => [
    'role:user', // leave blank to make this public
]], function () {
    // all your routes are prefixed with the above prefix
    // e.g. yoursite.com/sample
    Route::get('/', 'SampleController@index');
});
