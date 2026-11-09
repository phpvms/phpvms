{{-- The PIREP view's subtab panel (filament/pireps/partials/detail/index.blade.php) made reusable. --}}
@props(['key'])

<div
    id="tab-{{ $key }}"
    role="tabpanel"
    aria-labelledby="t-{{ $key }}"
    x-show="activeTab === @js($key)"
    x-cloak
    {{ $attributes }}
>
    {{ $slot }}
</div>
