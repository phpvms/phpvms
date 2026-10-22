<script setup lang="ts">
import { computed } from "vue";
import PirepData = App.Http.Data.PirepData;
import FlightDetailData = App.Http.Data.FlightDetailData;
import AirlineIdentityData = App.Http.Data.AirlineIdentityData;
import MapLiveFlightData = App.Http.Data.MapLiveFlightData;

type FlightCardSize = "sm" | "md" | "lg";

const props = withDefaults(
  defineProps<{
    pirep?: PirepData;
    flight?: FlightDetailData;
    airline?: AirlineIdentityData;
    live_flight?: MapLiveFlightData;

    size?: FlightCardSize;
    clickable?: boolean;
  }>(),
  {
    size: "md",
    clickable: false,
  },
);

const emit = defineEmits<{
  click: [event: MouseEvent];
}>();

const sizeClasses = computed(() => {
  switch (props.size) {
    case "sm":
      return {
        card: "max-w-sm rounded-2xl p-4",
        logo: "size-9 rounded-xl",
        logoImage: "size-7",
        airlineName: "hidden",
        flightNumber: "text-sm",
        airport: "text-2xl",
        city: "hidden",
        time: "text-xs",
        routeGap: "mx-4",
        plane: "size-7",
        planeIcon: "size-3.5",
        routeTop: "mt-5",
        statsTop: "mt-4 pt-3",
        statValue: "text-xs",
      };

    case "lg":
      return {
        card: "max-w-3xl rounded-3xl p-7",
        logo: "size-14 rounded-2xl",
        logoImage: "size-10",
        airlineName: "",
        flightNumber: "text-xl",
        airport: "text-4xl sm:text-5xl",
        city: "text-sm",
        time: "text-base sm:text-lg",
        routeGap: "mx-4 sm:mx-8",
        plane: "size-10 sm:size-11",
        planeIcon: "size-5",
        routeTop: "mt-10",
        statsTop: "mt-9",
        statValue: "text-base",
      };

    default:
      return {
        card: "max-w-xl rounded-[20px] p-5",
        logo: "size-11 rounded-xl",
        logoImage: "size-8",
        airlineName: "",
        flightNumber: "text-base",
        airport: "text-4xl",
        city: "text-xs",
        time: "text-sm",
        routeGap: "mx-5",
        plane: "size-9",
        planeIcon: "size-4",
        routeTop: "mt-7",
        statsTop: "mt-6",
        statValue: "text-sm",
      };
  }
});

/**
 * The card renders one flight from whichever source the caller passes. The
 * sources are ordered most-specific first: a PIREP describes a flight that was
 * actually flown, a live flight one in progress, and a FlightDetailData one
 * that is only scheduled. `airline` is a separate identity override, so it wins
 * over whatever airline the flight source happens to carry.
 */
const airlineRef = computed(
  () => props.live_flight?.airline ?? props.flight?.summary.airline ?? null,
);

const airlineName = computed(
  () => props.airline?.name ?? props.pirep?.airline ?? airlineRef.value?.name ?? null,
);

const airlineLogo = computed(() => props.airline?.logo ?? airlineRef.value?.logo ?? null);

const flightNumber = computed(
  () => props.pirep?.ident ?? props.live_flight?.ident ?? props.flight?.summary.callsign ?? "",
);

const routeCode = computed(() => props.flight?.summary.routeCode ?? null);

const status = computed(
  () =>
    props.pirep?.status ??
    props.pirep?.state ??
    props.live_flight?.status ??
    props.live_flight?.phase ??
    null,
);

const aircraft = computed(
  () =>
    props.pirep?.aircraft ??
    props.live_flight?.aircraft?.name ??
    props.live_flight?.aircraft?.registration ??
    null,
);

/**
 * FlightDetailData carries the airport twice -- the resolved `departure` point
 * and the flat `summary.dpt` code. Prefer the resolved one, which also supplies
 * the name shown under the code.
 */
const dpt = computed(
  () =>
    props.pirep?.dpt ??
    props.live_flight?.dptAirport?.icao ??
    props.flight?.departure?.icao ??
    props.flight?.summary.dpt ??
    "",
);

const arr = computed(
  () =>
    props.pirep?.arr ??
    props.live_flight?.arrAirport?.icao ??
    props.flight?.arrival?.icao ??
    props.flight?.summary.arr ??
    "",
);

const dptCity = computed(
  () =>
    props.pirep?.dptName ??
    props.live_flight?.dptAirport?.name ??
    props.flight?.departure?.name ??
    null,
);

const arrCity = computed(
  () =>
    props.pirep?.arrName ??
    props.live_flight?.arrAirport?.name ??
    props.flight?.arrival?.name ??
    null,
);

/**
 * Already display strings server-side -- every other consumer renders
 * `scheduledDeparture` verbatim (FlightManifest.vue:72), so no parsing here.
 * A PIREP has no scheduled times, hence flight-only.
 */
const dptTime = computed(
  () => props.flight?.scheduledDeparture ?? props.flight?.summary.scheduledDeparture ?? null,
);

const arrTime = computed(
  () => props.flight?.scheduledArrival ?? props.flight?.summary.scheduledArrival ?? null,
);

const flightTime = computed(
  () =>
    props.pirep?.flightTime ??
    props.pirep?.plannedFlightTime ??
    props.flight?.summary.blockTime ??
    null,
);

/** PIREP distances arrive pre-formatted; FlightListItemData carries a raw number. */
const distance = computed(() => {
  const flown = props.pirep?.distance ?? props.pirep?.plannedDistance;
  if (flown) {
    return flown;
  }

  const nm = props.flight?.summary.distanceNm;
  return nm == null ? null : `${nm} NM`;
});

/**
 * No source object carries these three, so the markup that renders them stays
 * inert until one grows the field: none of PirepData, FlightDetailData,
 * FlightListItemData or MapLiveFlightData has a leg number or an airport
 * timezone.
 */
const leg: string | number | null = null;
const dptTimezone: string | null = null;
const arrTimezone: string | null = null;

const hasStats = computed(() => Boolean(flightTime.value || distance.value || aircraft.value));
</script>

<template>
  <article
    class="group relative w-full overflow-hidden border border-zinc-200 bg-white text-zinc-950 shadow-[0_1px_2px_rgba(0,0,0,0.03),0_8px_30px_rgba(0,0,0,0.04)] transition duration-200 dark:border-white/10 dark:bg-zinc-900 dark:text-white dark:shadow-[0_12px_40px_rgba(0,0,0,0.25)]"
    :class="[
      sizeClasses.card,
      clickable
        ? 'cursor-pointer hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-[0_14px_40px_rgba(0,0,0,0.08)] dark:hover:border-white/20'
        : '',
    ]"
    @click="emit('click', $event)"
  >
    <!-- Subtle card highlight -->
    <div
      class="pointer-events-none absolute inset-x-10 top-0 h-px bg-linear-to-r from-transparent via-zinc-400/30 to-transparent dark:via-white/20"
    />

    <!-- Header -->
    <div class="relative flex items-start justify-between gap-4">
      <div class="flex min-w-0 items-center gap-3">
        <!-- Optional airline logo -->
        <div
          v-if="airlineLogo"
          class="flex shrink-0 items-center justify-center border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/5"
          :class="sizeClasses.logo"
        >
          <img
            :src="airlineLogo"
            :alt="airlineName || ''"
            class="object-contain"
            :class="sizeClasses.logoImage"
          />
        </div>

        <div class="min-w-0">
          <div
            v-if="airlineName"
            class="truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500"
            :class="sizeClasses.airlineName"
          >
            {{ airlineName }}
          </div>

          <div
            class="flex flex-wrap items-center gap-2"
            :class="{ 'mt-0.5': airlineName && size !== 'sm' }"
          >
            <span class="font-semibold tracking-tight" :class="sizeClasses.flightNumber">
              {{ flightNumber }}
            </span>

            <span
              v-if="routeCode"
              class="rounded-md bg-zinc-100 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-zinc-500 dark:bg-white/10 dark:text-zinc-400"
            >
              {{ routeCode }}
            </span>

            <span
              v-if="leg !== undefined && leg !== null && leg !== ''"
              class="text-[10px] font-medium uppercase tracking-wide text-zinc-400"
            >
              Leg {{ leg }}
            </span>
          </div>
        </div>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <span
          v-if="status"
          class="hidden rounded-full border border-zinc-200 px-2.5 py-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-zinc-400 sm:inline-flex dark:border-white/10 dark:text-zinc-500"
        >
          {{ status }}
        </span>

        <!-- Header actions, e.g. ellipsis button -->
        <slot name="header-actions" />
      </div>
    </div>

    <!-- Route -->
    <div class="relative grid grid-cols-[auto_1fr_auto] items-center" :class="sizeClasses.routeTop">
      <!-- Departure -->
      <div class="min-w-0">
        <div class="font-semibold tracking-[-0.055em]" :class="sizeClasses.airport">
          {{ dpt }}
        </div>

        <div
          v-if="dptCity"
          class="mt-1 truncate text-zinc-400"
          :class="[sizeClasses.city, { hidden: size === 'sm' }]"
        >
          {{ dptCity }}
        </div>

        <div
          v-if="dptTime"
          class="mt-2 flex items-baseline gap-1.5 font-semibold tabular-nums"
          :class="sizeClasses.time"
        >
          <span>{{ dptTime }}</span>

          <span
            v-if="dptTimezone && size === 'lg'"
            class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400"
          >
            {{ dptTimezone }}
          </span>
        </div>
      </div>

      <!-- Route line -->
      <div class="relative flex items-center" :class="sizeClasses.routeGap">
        <div
          class="absolute inset-x-0 border-t border-dashed border-zinc-300 dark:border-zinc-700"
        />

        <span class="absolute left-0 size-1.5 rounded-full bg-zinc-300 dark:bg-zinc-600" />

        <div
          class="relative mx-auto flex items-center justify-center rounded-full border border-zinc-200 bg-white shadow-sm transition-transform duration-300 group-hover:translate-x-1 dark:border-zinc-700 dark:bg-zinc-800"
          :class="sizeClasses.plane"
        >
          <svg
            viewBox="0 0 24 24"
            fill="currentColor"
            class="rotate-90"
            :class="sizeClasses.planeIcon"
            aria-hidden="true"
          >
            <path
              d="M12 2 9.5 9 3 11.5V14l6.5-1 1 6-2.5 2V22l4-1.25L16 22v-1l-2.5-2 1-6 6.5 1v-2.5L14.5 9 12 2Z"
            />
          </svg>
        </div>

        <span class="absolute right-0 size-1.5 rounded-full bg-zinc-950 dark:bg-white" />
      </div>

      <!-- Arrival -->
      <div class="min-w-0 text-right">
        <div class="font-semibold tracking-[-0.055em]" :class="sizeClasses.airport">
          {{ arr }}
        </div>

        <div
          v-if="arrCity"
          class="mt-1 truncate text-zinc-400"
          :class="[sizeClasses.city, { hidden: size === 'sm' }]"
        >
          {{ arrCity }}
        </div>

        <div
          v-if="arrTime"
          class="mt-2 flex items-baseline justify-end gap-1.5 font-semibold tabular-nums"
          :class="sizeClasses.time"
        >
          <span>{{ arrTime }}</span>

          <span
            v-if="arrTimezone && size === 'lg'"
            class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400"
          >
            {{ arrTimezone }}
          </span>
        </div>
      </div>
    </div>

    <!-- Stats + actions -->
    <div
      v-if="hasStats || $slots.actions"
      class="border-t border-zinc-100 dark:border-white/5"
      :class="sizeClasses.statsTop"
    >
      <div
        class="flex items-center gap-4"
        :class="size === 'sm' ? '' : 'rounded-xl bg-zinc-50 px-4 py-3 dark:bg-white/[0.035]'"
      >
        <div v-if="flightTime" class="min-w-0 flex-1">
          <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-zinc-400">
            Flight time
          </div>

          <div class="mt-1 font-semibold tabular-nums" :class="sizeClasses.statValue">
            {{ flightTime }}
          </div>
        </div>

        <div v-if="flightTime && distance" class="h-8 w-px shrink-0 bg-zinc-200 dark:bg-white/10" />

        <div
          v-if="distance"
          class="min-w-0 flex-1"
          :class="{ 'text-right': !$slots.actions && !aircraft }"
        >
          <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-zinc-400">
            Distance
          </div>

          <div class="mt-1 font-semibold tabular-nums" :class="sizeClasses.statValue">
            {{ distance }}
          </div>
        </div>

        <div
          v-if="aircraft && (flightTime || distance)"
          class="h-8 w-px shrink-0 bg-zinc-200 dark:bg-white/10"
        />

        <div v-if="aircraft" class="min-w-0 flex-1" :class="{ 'text-right': !$slots.actions }">
          <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-zinc-400">
            Aircraft
          </div>

          <div class="mt-1 truncate font-semibold" :class="sizeClasses.statValue">
            {{ aircraft }}
          </div>
        </div>

        <div v-if="$slots.actions" class="ml-auto flex shrink-0 items-center gap-2" @click.stop>
          <slot name="actions" />
        </div>
      </div>
    </div>
  </article>
</template>
