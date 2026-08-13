{{--
  Login hero — rendered above the Filament login form via the
  panels::auth.login.form.before render hook (AdminPanelProvider).
  Centered heading + subtitle over the global body::after dot grid.
--}}
@php
    $banner = app(\App\Support\Branding::class)->banner();
@endphp

@if ($banner)
    <div
        class="fixed inset-0 -z-10 bg-cover bg-center"
        style="background-image: url('{{ $banner }}')"
    ></div>
@endif

<header class="mb-6 text-center">
    <h1
        class="font-display mb-3 text-[clamp(26px,3.5vw,34px)] font-semibold leading-[1.1] tracking-[-0.02em] text-[#1c1e21] dark:text-[#e3e3e3]"
    >
        {{ __('filament.login_heading') }}
    </h1>

    <p class="mx-auto max-w-md font-sans text-[15px] leading-[1.55] text-[#606770] dark:text-[#8a8a8a]">
        {{ __('filament.login_subtitle') }}
    </p>
</header>
