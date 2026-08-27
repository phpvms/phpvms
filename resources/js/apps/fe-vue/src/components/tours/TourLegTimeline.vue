<script setup lang="ts">
import { computed } from "vue";

/**
 * A tour's legs as a UTimeline: numbered markers that become checks once a
 * leg is flown, the run's current leg marked "Up next", and everything
 * before it drawn as completed by the timeline's own value mechanism.
 *
 * Shared by the tours index card and a tour's overview page. `legs` may be a
 * window of the tour rather than all of it (the card collapses long tours),
 * so progress is derived from the tour, never from the slice.
 */

const props = defineProps<{
  tour: App.Http.Data.TourListItemData;
  legs: App.Http.Data.TourLegData[];
}>();

interface LegTimelineItem {
  value: string;
  ident: string;
  dpt: string;
  arr: string;
  state?: "next" | "flown";
  icon?: string;
  avatar?: { text: string };
}

function isCurrent(leg: App.Http.Data.TourLegData): boolean {
  return props.tour.status === "in_progress" && leg.flightId === props.tour.activeLegFlightId;
}

const items = computed<LegTimelineItem[]>(() =>
  props.legs.map((leg) => ({
    value: leg.flightId,
    ident: leg.ident,
    dpt: leg.dpt,
    arr: leg.arr,
    state: leg.flown ? "flown" : isCurrent(leg) ? "next" : undefined,
    icon: leg.flown ? "i-tabler-check" : undefined,
    avatar: leg.flown ? undefined : { text: String(leg.routeLeg ?? "") },
  })),
);

/**
 * The timeline's active value: the current leg of a run in progress (legs
 * before it read as completed), one past the end for a finished run, none
 * otherwise.
 */
const activeValue = computed<string | number | undefined>(() => {
  if (props.tour.status === "completed") return props.tour.legs.length;
  if (props.tour.status === "in_progress") return props.tour.activeLegFlightId ?? undefined;
  return undefined;
});
</script>

<template>
  <UTimeline
    :items="items"
    :default-value="activeValue"
    color="primary"
    size="xs"
    class="pv-tour-legs"
    :ui="{ wrapper: 'mt-0 pb-4', title: 'leading-6' }"
  >
    <template #title="{ item }">
      <span class="leg-line">
        <span class="leg-ident">{{ (item as LegTimelineItem).ident }}</span>
        <span class="leg-route"
          >{{ (item as LegTimelineItem).dpt }} <span aria-hidden="true">→</span>
          <span class="sr-only">to</span> {{ (item as LegTimelineItem).arr }}</span
        >
        <span
          v-if="(item as LegTimelineItem).state"
          class="leg-state"
          :data-state="(item as LegTimelineItem).state"
          >{{ (item as LegTimelineItem).state === "next" ? "Up next" : "Flown" }}</span
        >
      </span>
    </template>
  </UTimeline>
</template>

<style scoped>
@layer components {
  .leg-line {
    display: inline-flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
  }
  .leg-ident {
    font-family: var(--pv-font-mono);
    font-weight: 650;
  }
  .leg-route {
    font-family: var(--pv-font-mono);
  }
  .leg-state {
    font-size: 0.6875rem;
    font-weight: 750;
    text-transform: uppercase;
  }
  .leg-state[data-state="next"] {
    color: var(--pv-accent);
  }
  .leg-state[data-state="flown"] {
    color: color-mix(in srgb, var(--pv-green) 70%, var(--pv-ink));
  }
  .sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
  }
}
</style>
