<script setup lang="ts">
/**
 * SampleBidsSlot — a THIRD-PARTY-style skylight PAGE SLOT component.
 *
 * WHAT A SLOT COMPONENT IS (vs a widget)
 * --------------------------------------
 * A dashboard *widget* (see the sibling SampleVueWidget) is a self-contained
 * card the PILOT chooses to place on their board. A *slot* component is injected
 * by an ADDON into a fixed extension point ("slot") that a first-party page
 * exposes, and it renders wherever that page draws its matching `<PvSlot>`.
 *
 * This particular component fills the per-row slot `bids.row.actions` on the
 * "My Bids" page (resources/js/apps/fe-vue/src/pages/Flights/Bids.vue). That page
 * renders, once per bid row:
 *
 *     <PvSlot name="bids.row.actions" :context="{ bid: row.bid, flight: row.flight }" />
 *
 * So this component is instantiated ONCE PER ROW and receives THAT row's data as
 * props. This is EXACTLY how the real external ACARS plugin hooks the bids table:
 * it injects a per-bid-row control that lets the pilot fly the bid via ACARS.
 *
 * HOW THE PROPS ARRIVE (the `@bid` / `@flight` context mechanism)
 * --------------------------------------------------------------
 * The addon's ServiceProvider registers this slot entry with:
 *
 *     'props' => ['bid' => '@bid', 'flight' => '@flight']
 *
 * A `@`-prefixed value is a REF. PvSlot resolves it against
 * `{ ...pageProps, ...context }` — and the per-row `context` here is
 * `{ bid, flight }` — so `@bid` reads this row's bid and `@flight` reads this
 * row's flight summary. The host does the resolution; this component just
 * declares the props it expects to receive. (See PvSlot.vue's docblock.)
 *
 * DELIBERATELY STANDALONE (copy-me into any repo)
 * -----------------------------------------------
 *  - Imports ONLY from 'vue'. That bare specifier is resolved by the SPA shell's
 *    import-map to the host's single shared Vue instance, so this slot never
 *    bundles its own Vue and never forks reactivity.
 *  - Imports NOTHING skylight-internal. --pv-* design tokens are USED (never
 *    imported) with legible fallbacks, so this module can be lifted into the
 *    external ACARS plugin repo unchanged.
 *
 * DISABLE-SAFETY: the slot entry only exists while the addon is enabled (its
 * ServiceProvider registers it in boot()). Disabled → no entry → PvSlot renders
 * nothing for this addon and this code never runs. Nothing is left dangling.
 */
import { computed, ref } from 'vue'

/**
 * Per-row props, delivered by the host from the registry `@`-refs above.
 * Kept as loose records because a third-party slot must tolerate the host DTO
 * evolving — read defensively, never assume a field is present.
 */
const props = defineProps<{
  bid?: Record<string, unknown>
  flight?: Record<string, unknown>
}>()

/**
 * The flight ident to show in the control. The BidsPresenter row exposes the
 * flight summary with a `callsign`; fall back through a couple of likely keys so
 * the badge stays meaningful even if the DTO shape shifts.
 */
const ident = computed<string>(() => {
  const f = props.flight ?? {}
  return (
    (f.callsign as string | undefined) ??
    (f.ident as string | undefined) ??
    (f.flightId as string | undefined) ??
    '—'
  )
})

/**
 * Fake local "connected to ACARS" state. The real ACARS plugin would drive this
 * from its own client/session; here it is a plain local ref that toggles on
 * click so the reference addon is demonstrably interactive without a backend.
 */
const connected = ref(false)
function toggle() {
  connected.value = !connected.value
}
</script>

<template>
  <!--
    Compact, inline control sized to live inside a table cell. Mimics an ACARS
    "connect / fly this bid" action. Everything is --pv-* token driven with
    fallbacks and set inline: the tokens are provided by the host theme (not the
    addon), so inline fallbacks keep it legible even if a token is missing. (The
    preset auto-injects the scoped CSS below via vite-plugin-css-injected-by-js,
    so there is no separate CSS file to load.)
  -->
  <button
    type="button"
    class="sbs"
    :data-connected="connected ? 'true' : 'false'"
    :title="connected
      ? `ACARS connected — ${ident}`
      : `Connect ACARS for ${ident}`"
    :style="{
      display: 'inline-flex',
      alignItems: 'center',
      gap: '5px',
      padding: '2px 8px',
      fontSize: '11px',
      lineHeight: '1.4',
      fontWeight: '500',
      cursor: 'pointer',
      whiteSpace: 'nowrap',
      borderRadius: 'var(--pv-radius-sm, 6px)',
      border: `1px solid ${connected
        ? 'var(--pv-accent, #4f8cff)'
        : 'var(--pv-line, #2a2f3a)'}`,
      color: connected
        ? 'var(--pv-accent, #4f8cff)'
        : 'var(--pv-ink-dim, #8a94a6)',
      background: connected
        ? 'var(--pv-accent-soft, rgba(79, 140, 255, 0.12))'
        : 'transparent',
    }"
    @click="toggle"
  >
    <!-- ◉ filled when connected, ◯ hollow when idle -->
    <span aria-hidden="true">{{ connected ? '◉' : '◯' }}</span>
    <span>ACARS</span>
    <span class="sbs-ident" :style="{ fontFamily: 'var(--pv-font-mono, ui-monospace, monospace)' }">{{ ident }}</span>
  </button>
</template>

<style scoped>
/*
 * Visual essentials are inlined above. This scoped block only adds a small
 * hover affordance. The build preset folds this CSS into the JS bundle and
 * auto-injects it at runtime (vite-plugin-css-injected-by-js) — there is no
 * separate CSS file to load; the inline styles above cover the essentials
 * regardless.
 */
.sbs:hover {
  border-color: var(--pv-accent, #4f8cff);
  color: var(--pv-accent, #4f8cff);
}
.sbs-ident {
  opacity: 0.85;
}
</style>
