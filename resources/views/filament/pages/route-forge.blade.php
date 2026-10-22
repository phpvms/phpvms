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

    A second attribute, data-prefill, appears only when the page was reached
    by a bundle-page deep link (?topology=&bundle=&bundle_name=&fresh=). It
    carries the whitelisted params as JSON; see the Page's readPrefill() and
    resources/js/apps/admin/routeforge/lib/prefill.ts.

    See resources/js/apps/admin/routeforge/main.tsx for the boot-fetch flow.
--}}
<x-filament-panels::page>
    <div
        id="routeforge-root"
        class="rf-root"
        data-boot-url="{{ $this->bootUrl }}"
        @if ($this->prefill !== [])
            data-prefill="{{ json_encode($this->prefill) }}"
        @endif
    ></div>

    @vite('resources/js/apps/admin/routeforge/main.tsx')
</x-filament-panels::page>
