<script setup lang="ts">
/**
 * A tiny great-circle sparkline (origin dot → arc → destination ring), drawn in
 * blue. Decorative EFIS accent for a flight strip's route.
 *
 * @unused Not yet imported by FlightStrip or any page. Retained as a future
 * decorative enhancement for the bids/flights tables.
 */
withDefaults(defineProps<{ width?: number; height?: number }>(), { width: 60, height: 32 });
</script>

<template>
  <svg :width="width" :height="height" viewBox="0 0 60 32" aria-hidden="true" class="arc">
    <path
      d="M 6 26 C 14 8, 38 6, 54 18"
      fill="none"
      stroke="var(--pv-accent)"
      stroke-width="1.5"
      stroke-linecap="round"
      opacity="0.8"
    />
    <circle cx="6" cy="26" r="2" fill="var(--pv-accent)" />
    <circle cx="54" cy="18" r="2" fill="none" stroke="var(--pv-accent)" stroke-width="1" />
  </svg>
</template>

<style scoped>
.arc {
  display: block;
}
</style>
