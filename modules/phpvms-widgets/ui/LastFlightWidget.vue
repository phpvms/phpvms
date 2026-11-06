<script setup lang="ts">
import { computed } from 'vue'

/**
 * Last-flight summary card. Fed the DashboardData `lastPirep` object
 * (LastPirepData, snake_case) via a `pirep => '@lastPirep'` ref resolved by the
 * Dashboard. `--pv-*` tokens carry fallbacks so it renders standalone.
 */
interface LastPirep {
  ident: string | null
  flight_time: number | null
  dpt_airport: { icao: string } | null
  arr_airport: { icao: string } | null
  aircraft: { registration: string | null; name: string | null } | null
  state: { label: string } | null
}
const props = defineProps<{ pirep?: LastPirep | null }>()
const block = computed(() => {
  const t = props.pirep?.flight_time
  if (t == null) return '—'
  return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`
})
</script>

<template>
  <div v-if="pirep" class="lf">
    <div class="top">
      <span class="cs">{{ pirep.ident ?? 'PIREP' }}</span>
      <span v-if="pirep.state" class="badge">{{ pirep.state.label }}</span>
    </div>
    <div class="route tnum">
      {{ pirep.dpt_airport?.icao ?? '—' }} <span class="arrow">→</span> {{ pirep.arr_airport?.icao ?? '—' }}
    </div>
    <dl class="meta">
      <div><dt>Block</dt><dd class="tnum">{{ block }}</dd></div>
      <div><dt>Aircraft</dt><dd>{{ pirep.aircraft?.registration ?? '—' }}</dd></div>
    </dl>
  </div>
  <div v-else class="empty">No flights logged yet</div>
</template>

<style scoped>
.lf { display: flex; flex-direction: column; gap: 8px; }
.top { display: flex; align-items: center; justify-content: space-between; }
.cs { font-family: var(--pv-font-mono, ui-monospace, monospace); font-size: 14px; font-weight: 600; color: var(--pv-ink, #22262f); }
.badge {
  font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: var(--pv-radius-full, 9999px);
  color: var(--pv-green, #16a34a); background: color-mix(in srgb, var(--pv-green, #16a34a) 12%, transparent);
}
.route { font-family: var(--pv-font-mono, ui-monospace, monospace); font-size: 13px; color: var(--pv-ink-dim, #6b7280); }
.route .arrow { color: var(--pv-ink-faint, #9aa1b2); }
.meta { display: flex; gap: 20px; }
.meta dt { font-size: 11px; color: var(--pv-ink-faint, #9aa1b2); text-transform: uppercase; letter-spacing: 0.05em; }
.meta dd { font-size: 13px; color: var(--pv-ink, #22262f); margin-top: 2px; }
.empty { font-size: 12px; color: var(--pv-ink-dim, #6b7280); }
</style>
