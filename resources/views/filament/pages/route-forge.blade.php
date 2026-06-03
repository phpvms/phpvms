{{--
    RouteForge admin page shell.

    The Filament Page (App\Filament\Pages\RouteForge) handles permissioning,
    navigation registration, and resolves the boot endpoint URL in mount().

    The shell renders ONLY the mount point (#routeforge-root) with a
    data-boot-url attribute carrying the absolute URL of
    /admin/route-forge/api/boot. The Preact SPA reads that attribute via
    main.tsx, fetches the boot envelope once, hydrates its store, and
    renders. No PHP-rendered data envelope, no window.* globals — the only
    server-injected payload in the HTML is the boot URL itself.

    See resources/js/admin/routeforge/main.tsx for the boot-fetch flow.
--}}
<x-filament-panels::page>
    <div
        id="routeforge-root"
        class="rf-root"
        data-boot-url="{{ $this->bootUrl }}"
    ></div>

    @vite('public/js/admin/routeforge/main.tsx')
</x-filament-panels::page>
