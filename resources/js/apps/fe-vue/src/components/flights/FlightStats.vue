<script setup lang="ts">
/**
 * The small label-over-value strip a flight card carries — ETE, distance,
 * expiry and friends. Generic on purpose: the caller names the facts, this
 * only owns their rhythm and typography (mono, tabular figures).
 */

import type { FlightStat } from "@/components/flights/types";

withDefaults(
  defineProps<{
    stats: FlightStat[];
    columns?: number;
  }>(),
  { columns: 3 },
);
</script>

<template>
  <dl class="pv-flight-stats" :style="{ '--stat-columns': columns }">
    <div v-for="stat in stats" :key="stat.label">
      <dt>{{ stat.label }}</dt>
      <dd>{{ stat.value }}</dd>
    </div>
  </dl>
</template>

<style scoped>
@layer components {
  .pv-flight-stats {
    display: grid;
    grid-template-columns: repeat(var(--stat-columns, 3), minmax(0, 1fr));
    gap: 8px;
    margin: 0;
    text-align: center;
  }
  .pv-flight-stats dt {
    color: var(--pv-ink-dim);
    font-size: 0.6875rem;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .pv-flight-stats dd {
    margin: 2px 0 0;
    color: var(--pv-ink);
    font-family: var(--pv-font-mono);
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
  }
}
</style>
