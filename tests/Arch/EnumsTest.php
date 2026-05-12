<?php

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

arch('enums are correctly defined')
    ->expect('App\Enums')
    ->enums()
    ->toImplement(HasLabel::class);

arch('HasSelect trait is correctly defined')
    ->expect(HasSelect::class)
    ->toBeTrait();
