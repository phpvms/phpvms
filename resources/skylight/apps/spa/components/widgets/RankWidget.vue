<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface Rank { from: string; to: string | null; pct: number }
const page = usePage()
const rank = computed(() => page.props.rank as Rank | null)
const pct = computed(() => Math.max(0, Math.min(100, Math.round(rank.value?.pct ?? 0))))
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
.from { font-weight: 600; color: var(--pv-ink); }
.to { color: var(--pv-ink-dim); }
.to .arrow { color: var(--pv-ink-faint); }
.to.top { color: var(--pv-green); font-weight: 500; }
.track { height: 8px; border-radius: var(--pv-radius-full); background: var(--pv-track); overflow: hidden; }
.fill { height: 100%; border-radius: var(--pv-radius-full); background: var(--pv-accent); transition: width 0.6s ease; }
.pct { font-size: 12px; color: var(--pv-ink-dim); }
.empty { font-size: 12px; color: var(--pv-ink-dim); }
</style>
