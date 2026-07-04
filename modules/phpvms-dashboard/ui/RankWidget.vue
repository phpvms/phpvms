<script setup lang="ts">
import { computed } from 'vue'

/**
 * Rank-progress bar. Fed the DashboardData `rank` object (RankProgressData) via
 * a `rank => '@rank'` ref resolved by the Dashboard (no usePage() in an ESM
 * addon widget). `--pv-*` tokens carry fallbacks so it renders standalone.
 */
interface Rank {
  from: string
  to: string | null
  pct: number
}
const props = defineProps<{ rank?: Rank | null }>()
const pct = computed(() => Math.max(0, Math.min(100, Math.round(props.rank?.pct ?? 0))))
</script>

<template>
  <div v-if="rank" class="rank">
    <div class="row">
      <span class="from">{{ rank.from }}</span>
      <span v-if="rank.to" class="to"><span class="arrow">→</span> {{ rank.to }}</span>
      <span v-else class="to top">Top rank</span>
    </div>
    <div class="track">
      <div class="fill" :style="{ width: rank.to ? `${pct}%` : '100%' }" />
    </div>
    <div class="pct tnum">{{ rank.to ? `${pct}% to promotion` : 'Max rank reached' }}</div>
  </div>
  <div v-else class="empty">No rank assigned</div>
</template>

<style scoped>
.rank { display: flex; flex-direction: column; gap: 8px; }
.row { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; font-size: 13px; }
.from { font-weight: 600; color: var(--pv-ink, #22262f); }
.to { color: var(--pv-ink-dim, #6b7280); }
.to .arrow { color: var(--pv-ink-faint, #9aa1b2); }
.to.top { color: var(--pv-green, #16a34a); font-weight: 500; }
.track { height: 8px; border-radius: var(--pv-radius-full, 9999px); background: var(--pv-track, #eef0f4); overflow: hidden; }
.fill { height: 100%; border-radius: var(--pv-radius-full, 9999px); background: var(--pv-accent, #067ec1); transition: width 0.6s ease; }
.pct { font-size: 12px; color: var(--pv-ink-dim, #6b7280); }
.empty { font-size: 12px; color: var(--pv-ink-dim, #6b7280); }
</style>
