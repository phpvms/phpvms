{{--
  Login hero — rendered above the Filament login form via the
  panels::auth.login.form.before render hook (AdminPanelProvider).
  Mirrors the installer hero (resources/views/filament/system/hero.blade.php)
  minus the eyebrow + dot grid (dot grid lives on body::after globally).
--}}
<header class="mb-6 text-center">
    <div class="mb-5 flex items-center justify-center gap-3">
        <img
            src="{{ asset('assets/img/logo_blue.svg') }}"
            alt="phpVMS"
            class="h-12 w-auto"
        />
        <span
            class="font-display text-[27.75px] font-semibold tracking-[-0.01em] text-[#444950] dark:text-[#e3e3e3]"
        >
            phpvms
        </span>
    </div>

    <h1
        class="font-display mb-3 text-[clamp(26px,3.5vw,34px)] font-semibold leading-[1.1] tracking-[-0.02em] text-[#1c1e21] dark:text-[#e3e3e3]"
    >
        {{ __('filament.login_heading') }}
    </h1>

    <p class="mx-auto max-w-md font-sans text-[15px] leading-[1.55] text-[#606770] dark:text-[#8a8a8a]">
        {{ __('filament.login_subtitle') }}
    </p>
</header>
