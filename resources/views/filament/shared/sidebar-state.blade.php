{{--
    Settles the sidebar's open/collapsed class before first paint.

    Filament marks .fi-main-sidebar with x-cloak because only localStorage knows
    whether the sidebar is open. Stock Filament ships no [x-cloak] CSS at all, so
    there the attribute is inert; this theme does define one (24 vendor views
    rely on it), which made the whole rail display:none until Alpine booted and
    pop back in on every full page load.

    theme.css keeps the sidebar painted through that window. This supplies the
    missing half: the class the layout keys off (.fi-body:has(.fi-sidebar-open)
    drives both rail width and the content margin), so the first paint is
    already the final state rather than a guess that snaps.

    Runs during parsing, immediately after the sidebar root opens
    (PanelsRenderHook::SIDEBAR_START), which is why currentScript can reach it.
    Matched on .fi-main-sidebar rather than the tag: Filament 5.7 changed that
    root from <aside> to <div id="fi-main-sidebar">, and the class is what has
    stayed stable.
    Filament uses the same trick further down its own sidebar view to pre-hide
    collapsed groups -- "Alpine.js loads too slow".

    Mirrors vendor/filament/filament/resources/js/stores/sidebar.js: under
    1024px the store always closes, and above it the settled value is
    isOpenDesktop (both default true, persisted by $persist().as() under the
    bare key). Alpine re-asserts the class on init, so a stale read self-corrects.
--}}
<script>
    (() => {
        const sidebar = document.currentScript?.closest('.fi-main-sidebar')

        if (! sidebar) {
            return
        }

        let isOpen = false

        if (window.innerWidth >= 1024) {
            try {
                isOpen = JSON.parse(localStorage.getItem('isOpenDesktop') ?? 'true') !== false
            } catch {
                isOpen = true
            }
        }

        sidebar.classList.toggle('fi-sidebar-open', isOpen)
    })()
</script>
