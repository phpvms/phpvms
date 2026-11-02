{{--
    PIREP list: the folder tabs, then the table as a full-width sheet. The
    filters live in the table toolbar's Filter dropdown and a row opens the
    slide-over panel (PeekAction).
--}}
<x-filament-panels::page>
    {{ $this->content }}

    {{ $this->table }}
</x-filament-panels::page>
