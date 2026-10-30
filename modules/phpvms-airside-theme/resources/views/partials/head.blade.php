@php($branding = app(\App\Support\Branding::class))
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<title>{{ $title ?? '' }} - {{ $branding->name() }}</title>

{{--
  TallStackUI's theme switch keeps the mode under `dark-theme`. Apply it before
  first paint so a dark session never flashes light, and again after every
  wire:navigate: the navigation copies the incoming page's <html> attributes,
  which carry no class, over the one Alpine set.
--}}
<script>
    (function() {
        var apply = function() {
            try {
                var mode = localStorage.getItem('dark-theme');
                var dark = mode === 'dark' || mode === 'true' || ((mode === null || mode === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        };

        apply();

        if (!window.airsideThemeNavigated) {
            window.airsideThemeNavigated = true;
            document.addEventListener('livewire:navigated', apply);
        }
    })();
</script>

{{-- Start of required lines block. DON'T REMOVE THESE LINES! They're required or might break things --}}
<meta name="base-url" content="{!! url('') !!}">
<meta name="api-key" content="{!! Auth::check() ? Auth::user()->api_key : '' !!}">
<meta name="csrf-token" content="{!! csrf_token() !!}">
{{-- End the required lines block --}}

<link rel="shortcut icon" href="{{ $branding->favicon() }}" />

@airsideVite(['resources/css/airside.css', 'resources/js/airside.js'])
<tallstackui:script />
@livewireStyles
