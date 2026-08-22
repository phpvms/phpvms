<div class="pirep-tab-fixture">
    TAB-PANEL-OK {{ $record->ident }}
    {{ isset($mapFeatures) || isset($logEntries) ? 'SCOPE-LEAKED' : 'SCOPE-CLEAN' }}
</div>
