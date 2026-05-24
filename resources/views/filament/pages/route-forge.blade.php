{{--
    RouteForge admin page shell.

    The Filament Page (App\Filament\Pages\RouteForge) handles permissioning,
    navigation registration, and config payload preparation in mount().

    The shell renders a single mount point (#routeforge-root) and exposes
    config to the TypeScript bundle via window.routeforgeConfig. All UI lives
    in the Preact app loaded by Vite. See resources/js/admin/routeforge/.
--}}
<x-filament-panels::page>
    <div id="routeforge-root" class="rf-root"></div>

    <script>
        window.routeforgeConfig = @json($this->config);
    </script>

    @vite('resources/js/admin/routeforge/main.tsx')
</x-filament-panels::page>
