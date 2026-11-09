@inject('shell', 'App\Features\Shell\ShellData')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme({ default: 'system' })" x-bind:class="{ 'dark': darkTheme }">

<head>
    @include('partials.head', ['title' => trim($__env->yieldContent('title'))])
    @yield('css')
    @yield('scripts_head')
</head>

{{-- The focused layout for sign-in, registration and account-state pages. Sections: title, css, scripts_head, content, scripts, card_width. --}}
<body class="min-h-dvh bg-dark-50 text-dark-900 antialiased dark:bg-dark-900 dark:text-dark-100">
    @php($branding = app(\App\Support\Branding::class))

    <div class="mx-auto flex min-h-dvh w-full flex-col items-center px-4 py-10 {{ trim($__env->yieldContent('card_width', 'max-w-[400px]')) }}">
        <a href="{{ url('/') }}" class="mb-6 flex items-center gap-3 no-underline" wire:navigate>
            <img src="{{ $branding->logo(96) }}" alt="" aria-hidden="true" class="size-10 object-contain" />
            <span class="text-base font-semibold">{{ $shell->appName() }}</span>
        </a>

        <main class="w-full flex-1">
            @yield('content')
        </main>

        <footer class="mt-8 flex w-full items-center justify-between text-xs text-dark-500 dark:text-dark-400">
            <span>Copyright {{ date('Y') }} {{ $shell->appName() }}</span>
            <span>Powered by <a class="text-primary-600 dark:text-primary-400" href="https://www.phpvms.net" target="_blank" rel="noopener">phpvms</a></span>
        </footer>
    </div>

    <x-vms::shell.toasts :shell="$shell" />

    @livewireScripts

    @yield('scripts')
</body>

</html>
