@php
    /** @var \App\Models\Pirep $record */
@endphp

@if (filled($record->notes))
    <div class="fi-pirep-detail-v2-card">
        <div class="fi-pirep-detail-v2-card-head">
            <h3>{{ __('common.notes') }}</h3>
        </div>
        <div class="fi-pirep-detail-v2-card-body">
            <div class="fi-pirep-detail-v2-prose">{!! $record->notes !!}</div>
        </div>
    </div>
@endif

