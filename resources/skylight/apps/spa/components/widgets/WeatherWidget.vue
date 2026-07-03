<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'

/**
 * Weather FILL widget for the `dashboard.sidebar` slot. Pulls its own data from
 * GET /api/weather/{icao} (never blocks first paint), transitioning loading →
 * success | error. Fail-visible: provider errors render a diagnostic, not a gap.
 */
interface WeatherSuccess {
  icao: string
  metar: string | null
  taf: string | null
  conditions: string | null
  temperature: string | null
  wind: string | null
}

const props = withDefaults(defineProps<{ icao?: string | null }>(), { icao: null })

type State =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'success'; data: WeatherSuccess }
  | { status: 'error'; message: string }

const state = ref<State>({ status: 'idle' })
let controller: AbortController | null = null

async function load() {
  const icao = (props.icao ?? '').trim()
  if (!icao) {
    state.value = { status: 'error', message: 'No ICAO code provided.' }
    return
  }
  controller?.abort()
  controller = new AbortController()
  state.value = { status: 'loading' }
  try {
    const res = await fetch(`/api/weather/${encodeURIComponent(icao)}`, {
      signal: controller.signal,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (!res.ok || 'error' in json) {
      state.value = { status: 'error', message: json?.message ?? `Weather unavailable (HTTP ${res.status})` }
      return
    }
    state.value = { status: 'success', data: json as WeatherSuccess }
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') return
    state.value = { status: 'error', message: err instanceof Error ? err.message : 'Unknown fetch error' }
  }
}

onMounted(load)
watch(() => props.icao, load)
onBeforeUnmount(() => controller?.abort())
</script>

<template>
  <div class="wx">
    <div class="wx-h">
      <span class="wx-t">METAR</span>
      <span class="wx-id">{{ icao ?? '—' }}</span>
    </div>

    <div v-if="state.status === 'idle' || state.status === 'loading'" class="wx-loading" role="status">
      <span class="spinner" aria-hidden="true" />
      <span>Loading {{ icao ?? '…' }}</span>
    </div>

    <template v-else-if="state.status === 'success'">
      <div v-if="state.data.conditions" class="row"><span class="k">COND</span><span class="v">{{ state.data.conditions }}</span></div>
      <div v-if="state.data.temperature" class="row"><span class="k">TEMP</span><span class="v">{{ state.data.temperature }}</span></div>
      <div v-if="state.data.wind" class="row"><span class="k">WIND</span><span class="v">{{ state.data.wind }}</span></div>
      <pre v-if="state.data.metar" class="metar">{{ state.data.metar }}</pre>
      <p v-if="!state.data.conditions && !state.data.temperature && !state.data.wind && !state.data.metar" class="none">
        No weather data for {{ state.data.icao }}.
      </p>
    </template>

    <div v-else class="wx-error" role="alert" data-weather-error>
      <strong>Weather unavailable</strong>
      {{ state.message }}
    </div>
  </div>
</template>

<style scoped>
.wx {
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 14px 16px;
  box-shadow: var(--pv-shadow-panel);
}
.wx-h {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 10px;
}
.wx-t {
  font-family: var(--pv-font-mono);
  font-size: calc(8px * var(--pv-type-scale));
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
}
.wx-id {
  font-family: var(--pv-font-mono);
  font-size: calc(12px * var(--pv-type-scale));
  color: var(--pv-cyan);
  letter-spacing: 0.08em;
}
.wx-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
  padding: 4px 0;
}
.spinner {
  width: 1em;
  height: 1em;
  border: 2px solid var(--pv-line);
  border-top-color: var(--pv-accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  flex-shrink: 0;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
.row {
  display: flex;
  gap: 8px;
  margin: 3px 0;
  align-items: baseline;
}
.k {
  font-family: var(--pv-font-mono);
  font-size: calc(8px * var(--pv-type-scale));
  letter-spacing: 0.14em;
  color: var(--pv-ink-dim);
  min-width: 3rem;
}
.v {
  font-family: var(--pv-font-mono);
  font-size: calc(12px * var(--pv-type-scale));
  color: var(--pv-ink);
}
.metar {
  margin-top: 10px;
  padding: 8px 10px;
  background: var(--pv-panel-inset);
  border-radius: var(--pv-radius-sm);
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
  color: var(--pv-ink);
  white-space: pre-wrap;
  word-break: break-word;
}
.none {
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
}
.wx-error {
  padding: 10px 12px;
  background: var(--pv-slot-error-bg);
  border: 1px solid var(--pv-slot-error-border);
  border-radius: var(--pv-radius-sm);
  color: var(--pv-slot-error-text);
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
}
.wx-error strong {
  display: block;
  margin-bottom: 4px;
}
</style>
