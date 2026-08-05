{{--
    Flight edit page header: identity strip + section links + flight switcher.

    Replaces the default Filament page header (heading + actions) on EditFlight.
    The strip is sticky; the section links scroll to (and expand) the matching
    section via its DOM id. The switcher jumps to another flight in the same
    bundle. Delete/ForceDelete/Restore header actions render on the right.
--}}
<div
    class="rf-edit-strip sticky top-3 z-20 mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    x-data="{
        active: 'flight-information',
        init() {
            const ids = @js($sectionIds);
            const spy = () => {
                let current = 'flight-information';
                for (const id of ids) {
                    const el = document.getElementById(id);
                    if (el && el.getBoundingClientRect().top <= 120) {
                        current = id;
                    }
                }
                this.active = current;
            };
            window.addEventListener('scroll', spy, { passive: true });
            spy();
        },
        go(id) {
            const el = document.getElementById(id);
            if (el) {
                window.dispatchEvent(new CustomEvent('expand-section', { detail: { id } }));
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
    }"
>
    {{-- Row 1: identity + actions --}}
    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
        <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">{{ $ident }}</span>

        <span class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary-500"></span>
            {{ $dptIcao }} → {{ $arrIcao }}
        </span>

        <span
            @class([
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium',
                'border-green-200 bg-green-50 text-green-700 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-400' => $statusColor === 'success',
                'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400' => $statusColor === 'danger',
                'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400' => $statusColor === 'warning',
            ])
        >
            <span
                @class([
                    'inline-block h-1.5 w-1.5 rounded-full',
                    'bg-green-500' => $statusColor === 'success',
                    'bg-red-500'   => $statusColor === 'danger',
                    'bg-amber-500' => $statusColor === 'warning',
                ])
            ></span>
            {{ $statusLabel }}
        </span>

        <div class="ml-auto flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span>Flight time <strong class="font-semibold text-gray-900 dark:text-white">{{ $flightTime }}</strong></span>
            <span>Distance <strong class="font-semibold text-gray-900 dark:text-white">{{ $distance }} nmi</strong></span>
            <span>Level <strong class="font-semibold text-gray-900 dark:text-white">FL{{ $level }}</strong></span>
            <span>Subfleets <strong class="font-semibold text-gray-900 dark:text-white">{{ $subfleetCount }}</strong></span>
        </div>

        @if ($headerActions)
            <x-filament::actions :actions="$headerActions" />
        @endif
    </div>

    {{-- Row 2: section links + flight switcher --}}
    <div class="flex items-center gap-1 border-t border-gray-100 pt-2 dark:border-gray-700">
        <nav class="flex flex-wrap items-center gap-0.5" aria-label="Flight sections">
            @foreach ($sectionLinks as $link)
                <a
                    href="#{{ $link['id'] }}"
                    @click.prevent="go('{{ $link['id'] }}')"
                    x-bind:class="active === '{{ $link['id'] }}' ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-100'"
                    class="rounded-md px-2.5 py-1 text-sm font-medium transition"
                >
                    {{ $link['label'] }}
                    @if (($link['count'] ?? null) !== null)
                        <span class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ $link['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="ml-auto w-56">
            <select
                x-data="{
                    flights: @js($bundleFlights),
                    value: '',
                }"
                x-on:change="if (value) window.location.href = value"
                class="block w-full rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                aria-label="Switch flight"
            >
                <option value="">{{ $currentFlightLabel }}</option>
                @foreach ($bundleFlights as $flight)
                    <option value="{{ $flight['url'] }}">{{ $flight['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
