<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    flight: App.Http.Data.FlightListItemData;
    aircraft?: string | null;
    href?: string;
    size?: "md" | "lg";
  }>(),
  { aircraft: null, size: "md" },
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
    :class="{ 'flight-ident-header--lg': size === 'lg' }"
    :to="href"
    :size="userSize"
    :avatar="avatar"
    :ui="{
      wrapper: 'min-w-0',
      name: 'flex min-w-0 items-baseline gap-2 leading-tight',
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
