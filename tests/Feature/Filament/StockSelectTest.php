<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;

test('stock multiple selects are searchable and preloaded when configured', function (): void {
    $field = Select::make('equipment')
        ->multiple()
        ->searchable()
        ->preload();

    expect($field->isMultiple())->toBeTrue()
        ->and($field->isSearchable())->toBeTrue()
        ->and($field->isPreloaded())->toBeTrue();
});

test('stock multiple selects keep their configured options', function (): void {
    $field = Select::make('equipment')
        ->multiple()
        ->options([
            '1' => 'Boeing 737-800',
            '2' => 'Airbus A320',
        ]);

    expect($field->getOptions())->toBe([
        '1' => 'Boeing 737-800',
        '2' => 'Airbus A320',
    ]);
});
