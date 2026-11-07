@php
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Js;

    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div wire:ignore>
        <div
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('infinite-select') }}"
            x-data="infiniteSelectFormComponent({
                state: $wire.$entangle('{{ $statePath }}'),
                placeholder: {{ Js::from($getPlaceholder() ?? '') }},
                initialLabel: {{ Js::from($getInitialLabel()) }},
                getPage: async (search, page) => {
                    return await Livewire.fireAction(
                        $wire.__instance,
                        'callSchemaComponentMethod',
                        [{{ Js::from($field->getKey()) }}, 'getPageForJs', { search, page }],
                        { async: true },
                    )
                },
                resolveLabel: async (value) => {
                    return await Livewire.fireAction(
                        $wire.__instance,
                        'callSchemaComponentMethod',
                        [{{ Js::from($field->getKey()) }}, 'getOptionLabelForJs', { value }],
                        { async: true },
                    )
                },
            })"
            class="fi-infinite-select"
        >
            <select x-ref="select"></select>
        </div>
    </div>
</x-dynamic-component>
