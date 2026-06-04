<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/acme-fixture', fn (): string => 'ok')->name('acme.fixture');
