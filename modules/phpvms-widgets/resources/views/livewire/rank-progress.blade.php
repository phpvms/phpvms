<div class="vms-rank">
    @if ($progress)
        <div class="vms-rank__names">
            <span>{{ $progress['from'] }}</span>
            <span>{{ $progress['to'] === null ? __('phpvmswidgets::widgets.rank_top') : '→ '.$progress['to'] }}</span>
        </div>
        <div class="vms-rank__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['pct'] }}">
            <div class="vms-rank__fill" style="width: {{ $progress['pct'] }}%"></div>
        </div>
        <div class="vms-rank__pct">
            {{ $progress['to'] === null ? __('phpvmswidgets::widgets.rank_max') : __('phpvmswidgets::widgets.rank_to', ['pct' => $progress['pct']]) }}
        </div>
    @else
        <p class="vms-widget-empty">{{ __('phpvmswidgets::widgets.rank_none') }}</p>
    @endif
</div>
