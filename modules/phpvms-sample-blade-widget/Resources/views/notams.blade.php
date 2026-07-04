{{--
    Station NOTAMs — a LAYOUT-LESS Blade fragment for the skylight dashboard.

    IMPORTANT: there is no @extends and no <html>/<body> here. The skylight host
    shell (resources/js/apps/fe/apps/spa/components/widgets/BladeWidget.vue, island
    mode) fetches this endpoint and injects the returned HTML straight into an
    element already on the dashboard. If you wrap this in a full layout it will
    render a page-inside-a-widget. Return markup only.

    STYLING: use the theme's `--pv-*` CSS custom properties (they inherit from the
    dashboard) with INLINE `style="…"` — NOT Tailwind utility classes. Tailwind v4
    only scans `resources/js/apps/fe/`, so utility classes used here are NOT in the
    built CSS and would silently do nothing. Inline styles + `--pv-*` tokens keep
    the fragment self-contained and native to skylight in light and dark themes
    without shipping any CSS of its own. (If you really need Tailwind utilities in
    an addon fragment, add this file's directory to the theme's Tailwind
    `@source` globs so its classes are scanned into the build.)

    THE FORM: write a plain <form method="get">. You do NOT wire up any fetch/AJAX.
    The shell intercepts the submit, serialises the fields, appends them as a query
    string, adds the X-CSRF-TOKEN header, re-fetches this same endpoint, and swaps
    the returned HTML in place. Your controller just reads $request->query('icao').
    The action points at the SAME route name the provider registered as `endpoint`.
--}}
<div style="display: flex; flex-direction: column; gap: 12px; color: var(--pv-ink);">

    {{-- Heading --}}
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <h3 style="margin: 0; font-size: 14px; font-weight: 600; color: var(--pv-ink);">
            Station NOTAMs
        </h3>
        <span style="font-size: 12px; font-family: var(--pv-font-mono, ui-monospace, monospace); color: var(--pv-ink-faint);">
            {{ $icao }}
        </span>
    </div>

    {{-- Lookup form. Plain GET form; the shell handles the submit (see header). --}}
    <form
        method="get"
        action="{{ route('sample-blade-widget.notams') }}"
        style="display: flex; align-items: center; gap: 8px;"
    >
        <input
            type="text"
            name="icao"
            value="{{ $icao }}"
            maxlength="4"
            autocomplete="off"
            placeholder="ICAO"
            aria-label="Station ICAO"
            style="
                width: 96px;
                padding: 4px 8px;
                font-size: 12px;
                font-family: var(--pv-font-mono, ui-monospace, monospace);
                text-transform: uppercase;
                background: var(--pv-panel-inset);
                border: 1px solid var(--pv-line);
                color: var(--pv-ink);
                border-radius: var(--pv-radius-sm);
            "
        />
        <button
            type="submit"
            style="
                padding: 4px 10px;
                font-size: 12px;
                font-weight: 500;
                cursor: pointer;
                border: none;
                background: var(--pv-accent-soft);
                color: var(--pv-accent);
                border-radius: var(--pv-radius-sm);
            "
        >
            Load
        </button>
    </form>

    {{-- NOTAM list, or an empty state when the station has none. --}}
    @if (empty($notams))
        {{-- Empty state: shown for unknown/quiet stations. --}}
        <div
            style="
                padding: 16px 12px;
                text-align: center;
                font-size: 12px;
                background: var(--pv-panel-inset);
                color: var(--pv-ink-dim);
                border-radius: var(--pv-radius-md);
            "
        >
            No active NOTAMs for
            <span style="font-family: var(--pv-font-mono, ui-monospace, monospace);">{{ $icao }}</span>.
        </div>
    @else
        <ul style="display: flex; flex-direction: column; gap: 8px; margin: 0; padding: 0; list-style: none;">
            @foreach ($notams as $notam)
                {{--
                    Map the (server-computed) severity to a theme colour token.
                    Doing this in the view keeps the palette consistent with the
                    rest of skylight; the controller stays presentation-free.
                --}}
                @php
                    $severityColor = match ($notam['severity']) {
                        'high'   => 'var(--pv-red)',
                        'medium' => 'var(--pv-amber)',
                        default  => 'var(--pv-green)',
                    };
                @endphp
                <li
                    style="
                        display: flex;
                        align-items: flex-start;
                        gap: 8px;
                        padding: 8px 12px;
                        background: var(--pv-panel-inset);
                        border-radius: var(--pv-radius-md);
                    "
                >
                    {{-- Severity dot --}}
                    <span
                        style="
                            margin-top: 4px;
                            height: 8px;
                            width: 8px;
                            flex-shrink: 0;
                            border-radius: 9999px;
                            background: {{ $severityColor }};
                        "
                        aria-hidden="true"
                    ></span>
                    <div style="display: flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 12px; font-family: var(--pv-font-mono, ui-monospace, monospace); color: var(--pv-ink-dim);">
                            {{ $notam['id'] }}
                        </span>
                        <span style="font-size: 12px; color: var(--pv-ink);">
                            {{ $notam['summary'] }}
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Reminder to readers: this whole block is fake, server-side data. --}}
    <p style="font-size: 11px; color: var(--pv-ink-faint);">
        Sample data — generated on the server, never in the browser.
    </p>
</div>
