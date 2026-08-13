<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    callsign: string;
    departure: string;
    arrival: string;
    airlineLogo?: string | null;
    airlineName?: string | null;
    aircraft?: string | null;
    href?: string;
    size?: "md" | "lg";
  }>(),
  { airlineLogo: null, airlineName: null, aircraft: null, size: "md" },
);

const airlineMark = computed(() => props.airlineName?.trim().slice(0, 2).toUpperCase() || "VA");
const userSize = computed(() => (props.size === "lg" ? "3xl" : "xl"));
const avatar = computed(() => ({
  src: props.airlineLogo ?? undefined,
  alt: props.airlineName ?? props.callsign,
  text: airlineMark.value,
  ui: { root: "rounded-md border border-line-strong bg-panel-inset", image: "object-contain" },
}));
</script>

<template>
  <UUser
    class="pv-flight-info flight-ident-header"
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
      <strong class="flight-ident-header__callsign">{{ callsign }}</strong>
      <span class="flight-ident-header__route">
        <span>{{ departure }}</span>
        <span aria-hidden="true">→</span>
        <span class="sr-only">to</span>
        <span>{{ arrival }}</span>
      </span>
    </template>

    <template v-if="aircraft" #description>{{ aircraft }}</template>
  </UUser>
</template>

<style scoped>
.flight-ident-header {
  max-width: 100%;
  color: var(--pv-ink);
}
.flight-ident-header__callsign {
  flex: none;
  color: var(--pv-accent);
  font-family: var(--pv-font-mono);
  font-weight: 750;
}
.flight-ident-header__route {
  display: inline-flex;
  min-width: 0;
  align-items: baseline;
  gap: 0.35rem;
  overflow: hidden;
  font-family: var(--pv-font-mono);
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
