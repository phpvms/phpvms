<script setup lang="ts">
/**
 * SampleVueWidget — a THIRD-PARTY-style skylight dashboard widget.
 *
 * This file is the entire UI this addon contributes to the SPA. It is built by
 * the addon widget preset (see ./vite.config.ts) into a pre-built ESM module at
 * `public/widgets/sample.js`, with `vue` EXTERNALIZED. At runtime the SPA does
 * `import('/ext/samplevuewidget/widgets/sample.js')` and renders this file's
 * DEFAULT export (see resources/js/apps/fe-vue/src/components/widgets/resolve.ts).
 *
 * DELIBERATELY STANDALONE:
 *  - It imports ONLY from 'vue'. That bare specifier is resolved by the SPA
 *    shell's import-map to the host's single shared Vue instance — so this
 *    widget never bundles its own Vue and never forks reactivity.
 *  - It imports NOTHING skylight-internal. Patterns (fetch state machine, error
 *    box, --pv-* tokens) are COPIED here, not imported, so this module can be
 *    lifted into any third-party repo unchanged. Compare with the first-party
 *    WxWidget.vue, which is free to import from '@/…' because it is bundled.
 *
 * DISABLE-SAFETY: this addon owns BOTH its UI (this file) and its data endpoint
 * (/api/sample-vue/ping). Disable the addon and its ServiceProvider never boots,
 * so the widget is never registered and this code never runs — nothing here is
 * left dangling in the host.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Static prop passed through the widget registry `props` map (see the addon's
 * ServiceProvider). It travels server → Inertia shared props → resolveWidget →
 * this component. Purely presentational here.
 */
const props = withDefaults(defineProps<{ label?: string }>(), {
  label: 'Sample Vue widget',
})

/**
 * Shape returned by this addon's own endpoint (SamplePingData → JSON).
 *
 * The canonical type is GENERATED from the PHP DTO by
 * `php artisan typescript:transform` into the host SPA at
 *   resources/js/apps/fe-vue/src/types/generated.d.ts
 * as the ambient global `Modules.SampleVueWidget.Http.Data.SamplePingData`.
 *
 * A first-party (bundled) widget would consume it directly, e.g.:
 *   type PingSuccess = Modules.SampleVueWidget.Http.Data.SamplePingData
 * But this widget is DELIBERATELY STANDALONE — it is built by its own vite
 * preset with `vue` externalized, lives outside the SPA tsconfig's scope, and
 * imports nothing host-internal so it can be lifted into any third-party repo.
 * So it keeps this hand-written mirror of the generated shape rather than
 * reaching into the host's generated types.
 */
interface PingSuccess {
  addon: string
  message: string
  time: string
}

/** Explicit fail-visible state machine — mirrors WxWidget.vue's pattern. */
type State =
  | { status: 'loading' }
  | { status: 'success'; data: PingSuccess }
  | { status: 'error'; message: string }

const state = ref<State>({ status: 'loading' })
let controller: AbortController | null = null

async function load() {
  controller?.abort()
  controller = new AbortController()
  state.value = { status: 'loading' }
  try {
    // The addon owns this endpoint — registered by its ServiceProvider.
    const res = await fetch('/api/sample-vue/ping', {
      signal: controller.signal,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const json = await res.json()
    if (!res.ok || (json && 'error' in json)) {
      state.value = {
        status: 'error',
        message: json?.message ?? `Unavailable (HTTP ${res.status})`,
      }
      return
    }
    state.value = { status: 'success', data: json as PingSuccess }
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') return
    state.value = {
      status: 'error',
      message: err instanceof Error ? err.message : 'Fetch error',
    }
  }
}

onMounted(load)
onBeforeUnmount(() => controller?.abort())
</script>

<template>
  <div class="svw">
    <div class="svw-head">
      <span class="svw-label">{{ props.label }}</span>
      <button class="svw-refresh" type="button" title="Reload" @click="load">↻</button>
    </div>

    <div v-if="state.status === 'loading'" class="svw-loading" role="status">
      <span class="svw-spin" aria-hidden="true" /> Loading…
    </div>

    <dl v-else-if="state.status === 'success'" class="svw-rows">
      <div><dt>Addon</dt><dd class="mono">{{ state.data.addon }}</dd></div>
      <div><dt>Message</dt><dd>{{ state.data.message }}</dd></div>
      <div><dt>Time</dt><dd class="mono">{{ state.data.time }}</dd></div>
    </dl>

    <div
      v-else
      class="svw-err"
      role="alert"
      data-sample-vue-error
    >{{ state.message }}</div>
  </div>
</template>

<style scoped>
/*
 * Styled with the host's --pv-* design tokens (defined by the skylight theme)
 * so an addon widget matches the Workspace look with zero shared CSS. If a token
 * is missing (widget rendered outside skylight), the fallbacks keep it legible.
 */
.svw { display: flex; flex-direction: column; gap: 10px; }
.svw-head { display: flex; align-items: baseline; justify-content: space-between; }
.svw-label {
  font-size: 13px; font-weight: 500;
  color: var(--pv-accent, #4f8cff);
}
.svw-refresh {
  background: none; border: none; cursor: pointer; padding: 0 2px;
  font-size: 13px; line-height: 1; color: var(--pv-ink-dim, #8a94a6);
}
.svw-refresh:hover { color: var(--pv-accent, #4f8cff); }
.svw-loading {
  display: flex; align-items: center; gap: 8px;
  font-size: 12px; color: var(--pv-ink-dim, #8a94a6);
}
.svw-spin {
  width: 12px; height: 12px;
  border: 2px solid var(--pv-line, #2a2f3a);
  border-top-color: var(--pv-accent, #4f8cff);
  border-radius: 50%; animation: svw-spin 0.8s linear infinite;
}
@keyframes svw-spin { to { transform: rotate(360deg); } }
.svw-rows { display: flex; flex-direction: column; gap: 4px; margin: 0; }
.svw-rows div { display: flex; gap: 10px; }
.svw-rows dt {
  font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;
  min-width: 62px; color: var(--pv-ink-faint, #6b7280);
}
.svw-rows dd { margin: 0; font-size: 13px; color: var(--pv-ink, #e6e9ef); }
.mono { font-family: var(--pv-font-mono, ui-monospace, monospace); }
.svw-err {
  font-size: 12px; padding: 8px 10px;
  color: var(--pv-slot-error-text, #ffb4b4);
  background: var(--pv-slot-error-bg, rgba(255, 80, 80, 0.08));
  border: 1px solid var(--pv-slot-error-border, rgba(255, 80, 80, 0.3));
  border-radius: var(--pv-radius-sm, 6px);
}
</style>
