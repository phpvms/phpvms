<script setup lang="ts">
import { computed } from "vue";

/**
 * Rank-progress tape: current rank, next rank, and a green fill bar showing
 * percent toward the next rank. When `next` is null the pilot is at top rank.
 */
const props = withDefaults(defineProps<{ from: string; to?: string | null; pct?: number }>(), {
  to: null,
  pct: 0,
});

const clamped = computed(() => Math.max(0, Math.min(100, Math.round(props.pct))));
</script>

<template>
  <div class="rank">
    <div class="head">
      <span class="title">Rank Progress</span>
      <span class="pct">{{ to ? `${clamped}%` : "MAX" }}</span>
    </div>
    <div class="from">{{ from }}</div>
    <div class="to">{{ to ? `▸ ${to}` : "▸ TOP RANK" }}</div>
    <div class="track">
      <div
        class="fill"
        role="progressbar"
        :aria-valuenow="clamped"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-label="to ? `${clamped}% toward ${to}` : 'Top rank reached'"
        :style="{ width: to ? `${clamped}%` : '100%' }"
      />
    </div>
  </div>
</template>

<style scoped>
.rank {
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 14px 16px;
  box-shadow: var(--pv-shadow-panel);
}
.head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 10px;
}
.title {
  font-family: var(--pv-font-mono);
  font-size: calc(8px * var(--pv-type-scale));
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
}
.pct {
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
  color: var(--pv-green);
}
.from {
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
  margin-bottom: 2px;
  text-transform: uppercase;
}
.to {
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
  color: var(--pv-ink);
  font-weight: 500;
  letter-spacing: 0.04em;
  margin-bottom: 10px;
  text-transform: uppercase;
}
.track {
  height: 3px;
  background: var(--pv-track);
  border-radius: 2px;
  overflow: hidden;
}
.fill {
  height: 100%;
  background: var(--pv-green);
  border-radius: 2px;
  transition: width 1s ease;
}
</style>
