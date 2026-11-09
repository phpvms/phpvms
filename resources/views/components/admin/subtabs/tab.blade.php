{{-- The PIREP view's subtab button (filament/pireps/partials/detail/index.blade.php) made reusable. --}}
@props(['key', 'icon' => null, 'badge' => null])

<button
    type="button"
    class="subtab"
    role="tab"
    :aria-selected="activeTab === @js($key)"
    aria-controls="tab-{{ $key }}"
    id="t-{{ $key }}"
    @click="activeTab = @js($key)"
    {{ $attributes }}
>
    @if ($icon)
        @svg($icon)
    @endif
    {{ $slot }}
    @if ($badge !== null)
        <em>{{ $badge }}</em>
    @endif
</button>
