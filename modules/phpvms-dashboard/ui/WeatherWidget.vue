<script setup lang="ts">
/**
 * WeatherWidget — the skylight dashboard weather (METAR) widget, shipped by the
 * first-party `phpvms/phpvms-dashboard` addon.
 *
 * This was previously a BUNDLED first-party widget (WxWidget.vue) that read the
 * station from `usePage()`. It now ships as a pre-built ESM module (see
 * ./vite.config.ts) with `vue` EXTERNALIZED. At runtime the SPA does
 * `import('/ext/phpvmsdashboard/widgets/weather.js')` and renders this file's
 * default export.
 *
 * DELIBERATELY STANDALONE:
 *  - It imports ONLY from 'vue'. That bare specifier resolves via the SPA
 *    shell's import-map to the host's single shared Vue instance — no forked
 *    reactivity, no duplicate Vue.
 *  - It imports NOTHING skylight-internal, and CRUCIALLY it does NOT import
 *    inertia / usePage(). It receives the live station through an `icao` PROP:
 *    the addon registers `props: { icao: '@currentAirport' }` and the Dashboard
 *    resolves that `@`-ref against the page DTO before binding.
 *  - It fetches this addon's OWN endpoint (literal path, no route() helper).
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * The station to report weather for. Delivered as a resolved `@currentAirport`
 * ref from the widget registry → Dashboard prop resolution. May be null/absent
 * when the pilot has no current airport.
 */
const props = defineProps<{ icao?: string | null }>()

/** Success shape returned by this addon's endpoint (WeatherData → JSON). */
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

const state = ref<State>({ status: 'idle' })
let controller: AbortController | null = null

async function load() {
  const code = (props.icao ?? '').trim()
  if (!code) {
    state.value = { status: 'error', message: 'No ICAO available.' }
    return
  }
  controller?.abort()
  controller = new AbortController()
  state.value = { status: 'loading' }
  try {
    // The addon owns this endpoint — registered by its ServiceProvider.
    const res = await fetch(`/api/phpvms-dashboard/weather/${encodeURIComponent(code)}`, {
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
watch(() => props.icao, load)
onBeforeUnmount(() => controller?.abort())
</script>

<template>
  <div class="wx">
    <div class="wxid">
      <span class="micro">Station</span>
      <span class="code tnum">{{ props.icao ?? '—' }}</span>
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
/*
 * Styled with the host's --pv-* design tokens (defined by the skylight theme)
 * so this addon widget matches the Workspace look with zero shared CSS. Legible
 * fallbacks keep it usable if a token is ever missing.
 */
.wx { display: flex; flex-direction: column; gap: 10px; }
.wxid { display: flex; align-items: baseline; justify-content: space-between; }
.micro { font-size: 11px; color: var(--pv-ink-faint, #6b7280); text-transform: uppercase; letter-spacing: 0.05em; }
.code { font-family: var(--pv-font-mono, ui-monospace, monospace); font-size: 14px; color: var(--pv-accent, #4f8cff); font-weight: 500; }
.loading { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--pv-ink-dim, #8a94a6); }
.spin {
  width: 12px; height: 12px; border: 2px solid var(--pv-line, #2a2f3a); border-top-color: var(--pv-accent, #4f8cff);
  border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.rows { display: flex; flex-direction: column; gap: 4px; margin: 0; }
.rows div { display: flex; gap: 10px; }
.rows dt { font-size: 11px; color: var(--pv-ink-faint, #6b7280); text-transform: uppercase; letter-spacing: 0.05em; min-width: 46px; }
.rows dd { margin: 0; font-size: 13px; color: var(--pv-ink, #e6e9ef); }
.metar {
  font-family: var(--pv-font-mono, ui-monospace, monospace); font-size: 11px; color: var(--pv-ink-dim, #8a94a6);
  background: var(--pv-panel-inset, rgba(0, 0, 0, 0.04)); border-radius: var(--pv-radius-sm, 6px); padding: 8px 10px;
  white-space: pre-wrap; word-break: break-word;
}
.err {
  font-size: 12px; color: var(--pv-slot-error-text, #ffb4b4); background: var(--pv-slot-error-bg, rgba(255, 80, 80, 0.08));
  border: 1px solid var(--pv-slot-error-border, rgba(255, 80, 80, 0.3)); border-radius: var(--pv-radius-sm, 6px); padding: 8px 10px;
}
</style>
