<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;

/**
 * Adds an Expand / Collapse hint action to a {@see CodeEditor}.
 *
 * Expanding is client-side only: the action toggles `.fi-editor-expanded` on
 * the field wrapper (styled in the admin theme.css), so it is the same DOM
 * node and the editor keeps its value and Livewire binding.
 */
final class ExpandableEditor
{
    public static function make(CodeEditor $editor): CodeEditor
    {
        $expand = __('filament.editor.expand');
        $collapse = __('filament.editor.collapse');

        $toggle = <<<'JS'
            (() => {
                const field = $el.closest('[data-field-wrapper]');
                if (! field) { return; }
                const on = field.classList.toggle('fi-editor-expanded');
                document.documentElement.classList.toggle('fi-editor-expanded-lock', on);
                $el.dataset.expanded = on ? '1' : '';
                const label = $el.querySelector('.fi-link-label');
                if (label) { label.textContent = on ? $el.dataset.collapse : $el.dataset.expand; }
            })()
            JS;

        return $editor->hintAction(
            Action::make($editor->getName().'_expand')
                ->label($expand)
                ->icon(Phosphor::ArrowsOutLight)
                ->link()
                ->alpineClickHandler($toggle)
                ->extraAttributes([
                    'data-expand'                => $expand,
                    'data-collapse'              => $collapse,
                    'x-on:keydown.escape.window' => '$el.dataset.expanded && $el.click()',
                ]),
        );
    }
}
