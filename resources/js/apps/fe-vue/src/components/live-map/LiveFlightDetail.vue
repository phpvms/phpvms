<script setup lang="ts">
import { computed } from "vue";
import PvFlightInfo from "@/components/flights/PvFlightInfo.vue";
import FlightStats from "@/components/flights/FlightStats.vue";
import type { FlightStat } from "@/components/flights/types";
import UButton from "@nuxt/ui/components/Button.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";
import FlightIdentHeader from "@/components/flights/FlightIdentHeader.vue";

/**
 * The selected-flight overlay card (map-surfaces spec, "Selecting a flight"
 * shows full flight info). Floats over the map rather than reflowing the
 * page (user decision, replacing an earlier full-width row below the fold)
 * — self-positioned: its root is `position: absolute`, so it only needs a
 * `position: relative` ancestor (`.map-pane` in the parent), nothing else.
 *
 * Reuses the existing flight-card building blocks (fe-vue/AGENTS.md "grep
 * components/ before writing a card or header") rather than hand-rolling:
 * `PvFlightInfo` for the callsign/route identity (the same shape
 * `BidCard.vue` uses `FlightIdentHeader` for, but that component expects
 * `FlightListItemData` — bid/schedule fields this live-map selection
 * doesn't have — where `PvFlightInfo`'s plain callsign/departure/arrival
 * props are the actual match), `FlightStats` for the rest.
 *
 * Doesn't swallow map interaction: the wrapping `.detail-overlay` is
 * `pointer-events: none`; only `.detail-card` re-enables `pointer-events:
 * auto`. A drag starting on the card is just a normal DOM drag inside that
 * element — it's a sibling positioned in front of the map canvas, not
 * something that forwards pointer events down to maplibre's own handlers,
 * so no separate "stop propagation" guard is needed once this split is
 * right.
 *
 * Narrow-viewport choice (design.md open question 7 — mobile is still open
 * for this whole change; this doesn't attempt to resolve it, only to not
 * make it worse): capped to `min(320px, 100% - 24px)` wide and
 * `min(50vh, 22rem)` tall with its own internal scroll, anchored to the
 * map's top-left corner. On a narrow phone that still leaves the right and
 * bottom of the globe reachable for panning/zooming rather than the card
 * covering the whole surface.
 */
const props = defineProps<{
  detail: App.Http.Data.MapPirepDetailData | null;
  loading: boolean;
  error: boolean;
}>();

const emit = defineEmits<{ close: [] }>();

const flight = computed<App.Http.Data.FlightListItemData>(() => {
  return {
    id: props.detail?.pirepId ?? "",
    callsign: props.detail?.callsign ?? "",
    dpt: props.detail?.airports?.dpt?.icao ?? "",
    arr: props.detail?.airports?.arr?.icao ?? "",
    distanceNm: null,
    blockTime: null,
    type: null,
    airline: null,
    bidId: null,
    scheduledDeparture: null,
    scheduledArrival: null,
    routeCode: null,
    availability: "",
    availabilityReason: null,
    primaryAction: "",
  } satisfies App.Http.Data.FlightListItemData;
});

const stats = computed<FlightStat[]>(() => {
  if (!props.detail) return [];
  const d = props.detail;
  const list: FlightStat[] = [
    { label: "Pilot", value: d.pilot?.name ?? d.pilot?.ident ?? "—" },
    { label: "Airline", value: d.airline?.name ?? "—" },
    { label: "Aircraft", value: d.aircraft?.registration ?? d.aircraft?.name ?? "—" },
    {
      label: "Status",
      value: [d.status, d.phase].filter(Boolean).join(" · ") || "—",
    },
    { label: "Cruise level", value: d.cruiseLevel != null ? `FL${d.cruiseLevel}` : "—" },
  ];
  if (d.remarks) list.push({ label: "Remarks", value: d.remarks });
  return list;
});
</script>

<template>
  <div class="detail-overlay">
    <UPageCard
      variant="outline"
      class="detail-card"
      :ui="{ container: 'p-3 sm:p-3', header: 'mb-2' }"
      aria-label="Selected flight"
    >
      <template #header>
        <div class="detail-header">
          <!--<PvFlightInfo
            v-if="detail"
            :callsign="detail.callsign ?? detail.ident"
            :departure="detail.airports.dpt?.icao ?? '—'"
            :arrival="detail.airports.arr?.icao ?? '—'"
            size="sm"
          />
          <span v-else class="detail-ident">…</span>
          <UButton
            size="xs"
            variant="ghost"
            color="neutral"
            icon="i-lucide-x"
            aria-label="Close"
            @click="emit('close')"
          />-->
        </div>
      </template>

      <template #description>
        <!--<FlightIdentHeader-->
        <!--    :flight="flight"-->
        <!--    :href="`/flights/${flight.id}`"-->
        <!--    stacked-->
        <!--/>-->
        <p v-if="loading" class="detail-status">Loading…</p>
        <p v-else-if="error" class="detail-status" role="alert">
          Flight details could not be loaded.
        </p>

        <FlightIdentHeader
          v-else-if="detail"
          :flight="flight"
          :href="`/flights/${flight.id}`"
          stacked
        />

        <FlightStats v-else-if="detail" :stats="stats" />
      </template>
    </UPageCard>
  </div>
</template>

<style scoped>
@layer components {
  .detail-overlay {
    position: absolute;
    inset: 12px auto auto 12px;
    z-index: 10;
    pointer-events: none;
    max-width: min(320px, calc(100% - 24px));
  }
  .detail-card {
    pointer-events: auto;
    max-height: min(50vh, 22rem);
    overflow-y: auto;
  }
  .detail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }
  .detail-ident {
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
  }
  .detail-status {
    margin: 0;
    color: var(--pv-ink-dim);
    font-size: calc(0.875rem * var(--pv-type-scale));
  }
}
</style>
