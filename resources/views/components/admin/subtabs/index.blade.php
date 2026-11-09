{{-- The PIREP view's subtabs pattern (filament/pireps/partials/detail/index.blade.php) made reusable. --}}
@props(['default', 'label' => 'Sections'])

<section class="panel" x-data="{ activeTab: @js($default) }" {{ $attributes }}>
    <div class="subtabs" role="tablist" aria-label="{{ $label }}">
        {{ $tabs }}
    </div>

    {{ $slot }}
</section>
