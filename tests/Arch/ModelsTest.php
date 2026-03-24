<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\Model;

/*

This is disabled at the moment cause our codebase doesn't support it.
We would need to move a bunch of files and add some factories...
TODO: Look into this in the future and enable it again. Factories for pivot tables?

arch('models')
    ->expect('App\Models')
    ->toHaveMethod('casts')
    ->ignoring([
        'App\Models\Concerns',
    ])
    ->toExtend(Illuminate\Database\Eloquent\Model::class)
    ->ignoring([
        'App\Models\Concerns',
    ])
    ->toOnlyBeUsedIn([
        'App\Concerns',
        'App\Console',
        'App\EventActions',
        'App\Filament',
        'App\Http',
        'App\Jobs',
        'App\Livewire',
        'App\Observers',
        'App\Mail',
        'App\Models',
        'App\Notifications',
        'App\Policies',
        'App\Providers',
        'App\Queries',
        'App\Rules',
        'App\Services',
        'Database\Factories',
    ])->ignoring([
        'App\Models\Concerns',
    ]);

arch('ensure factories', function (): void {
    $models = getModels();

    foreach ($models as $model) {
         @var \Illuminate\Database\Eloquent\Factories\HasFactory $model
        expect($model::factory())
            ->toBeInstanceOf(Illuminate\Database\Eloquent\Factories\Factory::class);
    }
});

arch('ensure datetime casts', function (): void {
    $models = getModels();

    foreach ($models as $model) {
         @var \Illuminate\Database\Eloquent\Factories\HasFactory $model
        $instance = $model::factory()->create();

        $dates = collect($instance->getAttributes())
            ->filter(fn ($_, $key): bool => str_ends_with((string) $key, '_at'));

        foreach ($dates as $key => $value) {
            expect($instance->getCasts())->toHaveKey($key, 'datetime');
        }
    }
});*/

/**
 * Get all models in the app/Models directory.
 *
 * @return array<int, class-string<Model>>
 */
function getModels(): array
{
    $models = glob(__DIR__.'/../../app/Models/*.php');

    if ($models === false) {
        return [];
    }

    return collect($models)
        ->map(fn ($file): string => 'App\Models\\'.basename($file, '.php'))->toArray();
}
