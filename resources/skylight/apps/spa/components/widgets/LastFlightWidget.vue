<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface LastPirep {
  ident: string | null
  flight_time: number | null
  dpt_airport: { icao: string } | null
  arr_airport: { icao: string } | null
  aircraft: { registration: string; name: string } | null
  state: { label: string } | null
}
const page = usePage()
const p = computed(() => page.props.lastPirep as LastPirep | null)
const block = computed(() => {
  const t = p.value?.flight_time
  if (t == null) return '—'
  return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`
})
</script>

<template>
  <div v-if="p" class="lf">
    <div class="top">
      <span class="cs">{{ p.ident ?? 'PIREP' }}</span>
      <span v-if="p.state" class="badge">{{ p.state.label }}</span>
    </div>
    <div class="route tnum">
      {{ p.dpt_airport?.icao ?? '—' }} <span class="arrow">→</span> {{ p.arr_airport?.icao ?? '—' }}
    </div>
    <dl class="meta">
      <div><dt>Block</dt><dd class="tnum">{{ block }}</dd></div>
      <div><dt>Aircraft</dt><dd>{{ p.aircraft?.registration ?? '—' }}</dd></div>
    </dl>
  </div>
  <div v-else class="empty">No flights logged yet</div>
</template>

<style scoped>
.lf { display: flex; flex-direction: column; gap: 8px; }
.top { display: flex; align-items: center; justify-content: space-between; }
.cs { font-family: var(--pv-font-mono); font-size: 14px; font-weight: 600; color: var(--pv-ink); }
.badge {
  font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: var(--pv-radius-full);
  color: var(--pv-green); background: color-mix(in srgb, var(--pv-green) 12%, transparent);
}
.route { font-family: var(--pv-font-mono); font-size: 13px; color: var(--pv-ink-dim); }
.route .arrow { color: var(--pv-ink-faint); }
.meta { display: flex; gap: 20px; }
.meta dt { font-size: 11px; color: var(--pv-ink-faint); text-transform: uppercase; letter-spacing: 0.05em; }
.meta dd { font-size: 13px; color: var(--pv-ink); margin-top: 2px; }
.empty { font-size: 12px; color: var(--pv-ink-dim); }
</style>
