<script setup lang="ts">
import IconPlaneInflight from "~icons/tabler/plane-inflight";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UEmpty from "@nuxt/ui/components/Empty.vue";
import UListbox from "@nuxt/ui/components/Listbox.vue";

/**
 * The live flights list: rows to select from, plus its own empty/error
 * states (map-surfaces spec, "No live flights"). Purely presentational —
 * fetching and polling stay in the page (`pages/LiveMap/Index.vue`).
 *
 * `UListbox` (fe-vue/AGENTS.md "check Nuxt UI before custom markup") is the
 * selection primitive here, not a hand-rolled `<ul>/<li>/<button>` — single
 * selection, active-row styling, and keyboard navigation come for free.
 * `:model-value` + `@update:model-value` rather than `v-model`, since this
 * isn't a two-way contract: the page owns `selectedPirepId`, this component
 * only ever reads it and asks for a change (vue-best-practices,
 * component-data-flow — v-model only for true two-way bindings).
 * `:ui="{ root: 'ring-0 rounded-none' }"` drops Listbox's own border/radius
 * so it doesn't double up with `.flight-list`'s panel chrome below it.
 */
defineProps<{
  flights: App.Http.Data.MapLiveFlightData[];
  selectedPirepId: string | null;
  loading: boolean;
  loadError: boolean;
}>();

const emit = defineEmits<{ select: [pirepId: string] }>();
</script>

<template>
  <aside class="flight-list" aria-label="Live flights">
    <UEmpty
      v-if="!loading && flights.length === 0"
      :icon="IconPlaneInflight"
      title="No flights are live right now"
      description="Flights appear here as pilots depart."
    />
    <p v-else-if="loadError" class="list-error" role="alert">
      Live flights could not be loaded. Retrying automatically.
    </p>
    <UListbox
      v-else
      :items="flights"
      value-key="pirepId"
      label-key="ident"
      :model-value="selectedPirepId ?? undefined"
      :ui="{ root: 'ring-0 rounded-none', content: 'max-h-none overflow-visible' }"
      @update:model-value="(pirepId) => pirepId && emit('select', pirepId as string)"
    >
      <template #item="{ item }">
        <span class="ident">{{ item.ident }}</span>
        <span class="od"
          >{{ item.dptAirport?.icao ?? "—" }} → {{ item.arrAirport?.icao ?? "—" }}</span
        >
        <UBadge v-if="item.phase" size="sm" variant="subtle" color="neutral">{{
          item.phase
        }}</UBadge>
      </template>
    </UListbox>
  </aside>
</template>

<style scoped>
@layer components {
  .flight-list {
    min-width: 0;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-lg);
    background: var(--pv-panel);
    padding: 12px;
    overflow-y: auto;
    max-height: 70vh;
  }
  .ident {
    color: var(--pv-accent);
    font-family: var(--pv-font-mono);
    font-weight: 600;
  }
  .od {
    overflow: hidden;
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    text-overflow: ellipsis;
  }
  .list-error {
    color: var(--pv-red);
    font-size: calc(0.875rem * var(--pv-type-scale));
  }
  @media (max-width: 720px) {
    .flight-list {
      max-height: 40vh;
    }
  }
}
</style>
