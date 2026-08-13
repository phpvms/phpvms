@php
    $widgetsBySection = $widgetsBySection ?? [];
    $canEdit = $canEdit ?? false;
    $canDrag = $canDrag ?? false;
    $pageFilters = $pageFilters ?? [];
@endphp

<div
    x-data="phpvmsDashboardGrid({
        templateColumns: {{ $template->columns }},
    })"
    class="dashboard-canvas is-readonly"
    style="grid-template-columns: repeat({{ $template->columns }}, minmax(0, 1fr));"
>
    @foreach ($template->sections as $section)
        <section
            class="dashboard-section"
            style="grid-column: span {{ $section->columns }}; grid-row: span {{ $section->rowSpan }};"
        ><div
                class="grid-stack"
                wire:ignore
                data-section="{{ $section->slug }}"
                gs-column="{{ $section->columns }}"
                gs-cell-height="{{ $section->rowHeight }}"
            >
                @foreach ($widgetsBySection[$section->slug] ?? [] as $widget)
                    <div
                        class="grid-stack-item"
                        gs-id="{{ $widget['id'] }}"
                        gs-x="{{ $widget['x'] }}"
                        gs-y="{{ $widget['y'] }}"
                        gs-w="{{ $widget['w'] }}"
                        gs-h="{{ $widget['h'] }}"
                        gs-min-w="{{ $widget['minW'] }}"
                        gs-max-w="{{ $widget['maxW'] }}"
                        gs-min-h="{{ $widget['minH'] }}"
                        gs-max-h="{{ $widget['maxH'] }}"
                    >
                        <div class="grid-stack-item-content">
                            @include('filament-dynamic-dashboard::schemas.widget-wrapper', [
                                'widget' => $widget,
                                'canEdit' => $canEdit,
                                'showLoader' => $widget['showLoader'] ?? true,
                                'pageFilters' => $pageFilters,
                            ])
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
