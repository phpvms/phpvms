<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface WeatherSuccess {
  icao: string
  metar: string | null
  conditions: string | null
  temperature: string | null
  wind: string | null
}
type State =
  | { status: 'idle' | 'loading' }
  | { status: 'success'; data: WeatherSuccess }
  | { status: 'error'; message: string }

const page = usePage()
const icao = computed(() => {
  const a = page.props.currentAirport as string | { icao?: string } | null | undefined
  return !a ? null : typeof a === 'string' ? a : (a.icao ?? null)
})

const state = ref<State>({ status: 'idle' })
let controller: AbortController | null = null

async function load() {
  const code = (icao.value ?? '').trim()
  if (!code) {
    state.value = { status: 'error', message: 'No ICAO available.' }
    return
  }
  controller?.abort()
  controller = new AbortController()
  state.value = { status: 'loading' }
  try {
    const res = await fetch(`/api/weather/${encodeURIComponent(code)}`, {
      signal: controller.signal,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (!res.ok || 'error' in json) {
      state.value = { status: 'error', message: json?.message ?? `Unavailable (HTTP ${res.status})` }
      return
    }
    state.value = { status: 'success', data: json as WeatherSuccess }
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') return
    state.value = { status: 'error', message: err instanceof Error ? err.message : 'Fetch error' }
  }
}

onMounted(load)
watch(icao, load)
onBeforeUnmount(() => controller?.abort())
</script>

<template>
  <div class="wx">
    <div class="wxid">
      <span class="micro">Station</span>
      <span class="code tnum">{{ icao ?? '—' }}</span>
    </div>

    <div v-if="state.status === 'loading' || state.status === 'idle'" class="loading" role="status">
      <span class="spin" aria-hidden="true" /> Loading…
    </div>

    <template v-else-if="state.status === 'success'">
      <dl class="rows">
        <div v-if="state.data.conditions"><dt>Cond</dt><dd>{{ state.data.conditions }}</dd></div>
        <div v-if="state.data.temperature"><dt>Temp</dt><dd class="tnum">{{ state.data.temperature }}</dd></div>
        <div v-if="state.data.wind"><dt>Wind</dt><dd class="tnum">{{ state.data.wind }}</dd></div>
      </dl>
      <pre v-if="state.data.metar" class="metar">{{ state.data.metar }}</pre>
    </template>

    <div v-else-if="state.status === 'error'" class="err" role="alert" data-weather-error>{{ state.message }}</div>
  </div>
</template>

<style scoped>
.wx { display: flex; flex-direction: column; gap: 10px; }
.wxid { display: flex; align-items: baseline; justify-content: space-between; }
.code { font-family: var(--pv-font-mono); font-size: 14px; color: var(--pv-accent); font-weight: 500; }
.loading { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--pv-ink-dim); }
.spin {
  width: 12px; height: 12px; border: 2px solid var(--pv-line); border-top-color: var(--pv-accent);
  border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.rows { display: flex; flex-direction: column; gap: 4px; }
.rows div { display: flex; gap: 10px; }
.rows dt { font-size: 11px; color: var(--pv-ink-faint); text-transform: uppercase; letter-spacing: 0.05em; min-width: 46px; }
.rows dd { font-size: 13px; color: var(--pv-ink); }
.metar {
  font-family: var(--pv-font-mono); font-size: 11px; color: var(--pv-ink-dim);
  background: var(--pv-panel-inset); border-radius: var(--pv-radius-sm); padding: 8px 10px;
  white-space: pre-wrap; word-break: break-word;
}
.err {
  font-size: 12px; color: var(--pv-slot-error-text); background: var(--pv-slot-error-bg);
  border: 1px solid var(--pv-slot-error-border); border-radius: var(--pv-radius-sm); padding: 8px 10px;
}
</style>
