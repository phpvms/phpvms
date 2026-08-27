<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    flight: App.Http.Data.FlightListItemData;
    aircraft?: string | null;
    href?: string;
    size?: "md" | "lg";
    /**
     * Put the route on its own line under the ident, and let a long ident
     * ellipsize rather than hold its full width. Off by default — on the
     * wide OFP pages the two read as one headline; inside a ~360px card
     * they don't fit side by side (AP9100/C.Est./L.2 alone is 163px).
     */
    stacked?: boolean;
  }>(),
  { aircraft: null, size: "md", stacked: false },
);

const airlineMark = computed(
  () => props.flight.airline?.name.trim().slice(0, 2).toUpperCase() || "VA",
);
const userSize = computed(() => (props.size === "lg" ? "3xl" : "xl"));
const avatar = computed(() => ({
  src: props.flight.airline?.logo ?? undefined,
  alt: props.flight.airline?.name ?? props.flight.callsign,
  text: airlineMark.value,
  ui: { root: "rounded-md border border-line-strong bg-panel-inset", image: "object-contain" },
}));
</script>

<template>
  <UUser
    class="pv-flight-info flight-ident-header"
    :class="{
      'flight-ident-header--lg': size === 'lg',
      'flight-ident-header--stacked': stacked,
    }"
    :to="href"
    :size="userSize"
    :avatar="avatar"
    :ui="{
      wrapper: 'min-w-0',
      name: stacked
        ? 'flex min-w-0 flex-col items-start leading-tight'
        : 'flex min-w-0 items-baseline gap-2 leading-tight',
      description: 'mt-0.5 truncate text-ink-dim',
    }"
  >
    <template #name>
      <span class="flight-ident-header__callsign">{{ flight.callsign }}</span>
      <span class="flight-ident-header__route">
        <span>{{ flight.dpt ?? "—" }}</span>
        <span aria-hidden="true">→</span>
        <span class="sr-only">to</span>
        <span>{{ flight.arr ?? "—" }}</span>
      </span>
    </template>

    <template v-if="aircraft" #description>
      <span class="flight-ident-header__aircraft">{{ aircraft }}</span>
    </template>
  </UUser>
</template>

<style scoped>
.flight-ident-header {
  max-width: 100%;
  color: var(--pv-ink);
}
.flight-ident-header__callsign {
  flex: none;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-weight: 700;
}
.flight-ident-header--stacked {
  min-width: 0;
}
.flight-ident-header--stacked .flight-ident-header__callsign {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.flight-ident-header--stacked .flight-ident-header__route {
  margin-top: 2px;
  max-width: 100%;
  font-weight: 500;
}
.flight-ident-header__route {
  display: inline-flex;
  min-width: 0;
  align-items: baseline;
  gap: 0.35rem;
  overflow: hidden;
  font-family: var(--pv-font-mono);
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.flight-ident-header--lg .flight-ident-header__callsign,
.flight-ident-header--lg .flight-ident-header__route {
  font-size: calc(1.5rem * var(--pv-type-scale));
}
.flight-ident-header__aircraft {
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
</style>
