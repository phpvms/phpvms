@props(['title' => null, 'description' => null])

{{-- The card every auth page sits in: hairline border, no shadow, optional footer slot. --}}
<section {{ $attributes->class('vms-auth-card w-full overflow-hidden rounded-xl') }}>
    <div class="grid gap-4 p-6">
        @if ($title)
            <header class="grid gap-1">
                <h1 class="text-lg font-semibold">{{ $title }}</h1>
                @if ($description)
                    <p class="text-[13px]">{{ $description }}</p>
                @endif
            </header>
        @endif

        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex flex-wrap justify-between gap-2 px-6 py-3 text-[13px]">
            {{ $footer }}
        </footer>
    @endisset
</section>
