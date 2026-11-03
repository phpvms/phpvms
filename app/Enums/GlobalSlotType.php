<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Support\Contracts\HasLabel;

/**
 * The content kind of a global slot row. A `css` row is wrapped in `<style>`
 * on output; an `html` row is emitted as written.
 */
enum GlobalSlotType: string implements HasLabel
{
    case CSS = 'css';
    case HTML = 'html';

    public function getLabel(): string
    {
        return match ($this) {
            self::CSS  => __('filament.global_slots.type_css'),
            self::HTML => __('filament.global_slots.type_html'),
        };
    }

    public function getEditorLanguage(): Language
    {
        return match ($this) {
            self::CSS  => Language::Css,
            self::HTML => Language::Html,
        };
    }
}
