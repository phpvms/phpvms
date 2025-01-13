<?php

// This is the admin path. Comment this out if you don't have
// an admin panel component.
Route::group([], function () {
    Route::get('/', 'AdminController@index');
    Route::get('/create', 'AdminController@create');
});
