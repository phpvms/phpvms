<div class="pirep-tab-fixture">
    TAB-PANEL-OK {{ $record->ident }}
    {{-- The registered view must receive ONLY the record, never the page scope. --}}
    {{ isset($mapFeatures) || isset($logEntries) ? 'SCOPE-LEAKED' : 'SCOPE-CLEAN' }}
</div>
