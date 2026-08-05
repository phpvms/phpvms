<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * Forwards the model template parameter to the Eloquent factory it extends.
 *
 * Without this, every `@extends Factory<Foo>` in database/factories/ declares a
 * generic argument on a class static analysis treats as non-generic, so the
 * model type is erased and `Foo::factory()->create()` resolves to the base
 * Model rather than to Foo.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends EloquentFactory<TModel>
 */
abstract class Factory extends EloquentFactory {}
