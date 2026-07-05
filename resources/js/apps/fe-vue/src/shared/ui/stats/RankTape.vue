<script setup lang="ts">
import { computed } from "vue";

/**
 * Rank-progress tape: current rank, next rank, and a green fill bar showing
 * percent toward the next rank. When `next` is null the pilot is at top rank.
 *
 * @unused Not currently imported by any page. Retained for future use when the
 * rank-progress KPI returns to the bundled dashboard (currently served by the
 * phpvms/phpvms-dashboard addon).
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
  border-radius: var(--pv-radius-lg);
  padding: 14px 16px;
}
.head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 10px;
}
.title {
  font-size: 11px;
  font-weight: 500;
  color: var(--pv-ink-dim);
}
.pct {
  font-family: var(--pv-font-mono);
  font-size: 11px;
  color: var(--pv-green);
  font-variant-numeric: tabular-nums;
}
.from {
  font-size: 12px;
  font-weight: 500;
  color: var(--pv-ink-dim);
  margin-bottom: 2px;
}
.to {
  font-size: 13px;
  font-weight: 600;
  color: var(--pv-ink);
  margin-bottom: 10px;
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
