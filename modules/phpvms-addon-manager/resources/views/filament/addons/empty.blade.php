{{-- Nothing selected, or the filters matched nothing to select. --}}
<div class="empty">
    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::PuzzlePieceLight" />
    <strong>{{ __('addon-manager::addons.nothing_selected') }}</strong>
    <span>{{ __('addon-manager::addons.nothing_selected_hint') }}</span>
</div>
