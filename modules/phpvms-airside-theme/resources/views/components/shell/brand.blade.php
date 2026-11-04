@props(['shell'])

{{-- The logo and app name link: the sidebar brand and the top bar's left end. --}}
<a href="{{ url('/') }}" {{ $attributes->class(['vms-shell-brand flex items-center gap-3 text-lg font-extrabold tracking-tight']) }} wire:navigate>
    <img src="{{ app(\App\Support\Branding::class)->logo(52) }}" alt="" aria-hidden="true" class="size-8 rounded-[10px] object-contain" />
    <span class="truncate">{{ $shell->appName() }}</span>
</a>
