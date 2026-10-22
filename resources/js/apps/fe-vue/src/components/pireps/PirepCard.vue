<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";
import { stateBadgeColor } from "@/components/pireps/stateBadgeColor";
import FlightStats from "@/components/flights/FlightStats.vue";
import type { FlightStat } from "@/components/flights/types";
import { formatPirepDay } from "@/widgets/pireps/formatPirepDate";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";

/**
 * One row of the logbook: ident + state, the route with airport names, and the
 * label-over-value stat strip every flight card in the app shares
 * (`FlightStats`). The whole card is the Inertia link to the PIREP.
 *
 * `:as`/`:href` rather than `to`: `UPageCard` forwards `$attrs` to its root only
 * when `to` is unset, and the logbook list needs `role="listitem"` to land on
 * the card itself. Both forms Inertia-navigate (the plugin runs in `router:
 * "inertia"` mode).
 */
const props = defineProps<{
  pirep: App.Http.Data.PirepListItemData;
}>();

const stats = computed<FlightStat[]>(() => [
  { label: "Aircraft", value: props.pirep.aircraft ?? "—" },
  { label: "Time", value: props.pirep.flightTime ?? "—" },
  { label: "Distance", value: props.pirep.distance ?? "—" },
  { label: "Score", value: props.pirep.score?.toString() ?? "—" },
  { label: "Filed", value: formatPirepDay(props.pirep.submittedAt) },
]);
</script>

<template>
  <UPageCard
    :as="Link"
    :href="`/pireps/${pirep.id}`"
    variant="outline"
    class="pv-pirep-card"
    :ui="{
      container: 'p-3 sm:p-4 gap-y-0',
      header: 'mb-2',
      body: 'flex-1',
      footer: 'pt-2.5 mt-2.5 border-t border-default',
    }"
  >
    <template #header>
      <div class="ident-row">
        <span class="ident">{{ pirep.ident }}</span>
        <UBadge :color="stateBadgeColor(pirep.stateColor)" variant="subtle" size="sm">
          {{ pirep.state }}
        </UBadge>
      </div>
    </template>

    <template #body>
      <div class="route">
        <span class="airport">
          <span class="icao">{{ pirep.dpt }}</span>
          <span class="name">{{ pirep.dptName ?? "" }}</span>
        </span>
        <span class="arrow" aria-hidden="true">→</span>
        <span class="airport airport--arrival">
          <span class="icao">{{ pirep.arr }}</span>
          <span class="name">{{ pirep.arrName ?? "" }}</span>
        </span>
      </div>
    </template>

    <template #footer>
      <FlightStats :stats="stats" :columns="5" />
    </template>
  </UPageCard>
</template>

<style scoped>
@layer components {
  .pv-pirep-card {
    text-decoration: none;
  }
  .ident-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
  }
  .ident {
    color: var(--pv-accent);
    font-family: var(--pv-font-mono);
    font-size: calc(0.8125rem * var(--pv-type-scale));
    font-weight: 650;
  }
  .route {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .airport {
    display: flex;
    min-width: 0;
    flex-direction: column;
  }
  .airport--arrival {
    text-align: right;
  }
  .icao {
    font-family: var(--pv-font-mono);
    font-size: calc(0.8125rem * var(--pv-type-scale));
    font-weight: 550;
  }
  .name {
    overflow: hidden;
    color: var(--pv-ink-dim);
    font-size: calc(0.6875rem * var(--pv-type-scale));
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .arrow {
    flex-shrink: 0;
    color: var(--pv-ink-faint);
  }
}
</style>
