<?php

declare(strict_types=1);
use Illuminate\Support\ServiceProvider;

arch('providers')
    ->expect('App\Providers')
    ->toExtend(ServiceProvider::class)
    ->not->toBeUsed();
