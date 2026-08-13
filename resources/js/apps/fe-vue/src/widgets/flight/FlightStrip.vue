<script setup lang="ts">
/**
 * A progress-strip style flight card: a colored spine tab, a callsign, a route
 * (DPT ▸ ARR), a set of mono data fields, and a trailing slot (status badge or
 * BID action). Modeled on ATC flight-progress strips.
 */
export interface StripField {
  label: string;
  value: string;
}

withDefaults(
  defineProps<{
    callsign: string;
    dpt?: string | null;
    arr?: string | null;
    fields?: StripField[];
    tab?: "mag" | "green" | "amber" | "dim";
    selected?: boolean;
  }>(),
  { dpt: null, arr: null, fields: () => [], tab: "mag", selected: false },
);
</script>

<template>
  <div class="strip" :class="{ selected }">
    <div class="tab" :class="tab" />
    <div class="body">
      <div class="field">
        <span class="fl">Callsign</span>
        <span class="val callsign">{{ callsign }}</span>
      </div>
      <div v-if="dpt || arr" class="field">
        <span class="fl">Route</span>
        <span class="val route">
          {{ dpt ?? "—" }}
          <span class="arrow">▸</span>
          {{ arr ?? "—" }}
        </span>
      </div>
      <div v-for="f in fields" :key="f.label" class="field">
        <span class="fl">{{ f.label }}</span>
        <span class="val">{{ f.value }}</span>
      </div>
      <div class="field trailing">
        <slot name="trailing" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.strip {
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  display: flex;
  align-items: stretch;
  overflow: hidden;
  transition:
    border-color 0.15s,
    background 0.15s;
}
.strip:hover {
  border-color: color-mix(in srgb, var(--pv-accent) 35%, var(--pv-line));
}
.strip.selected {
  border-color: color-mix(in srgb, var(--pv-accent) 50%, var(--pv-line));
  background: color-mix(in srgb, var(--pv-accent) 4%, var(--pv-panel));
}
.tab {
  width: 4px;
  flex-shrink: 0;
  background: var(--pv-accent);
}
.tab.green {
  background: var(--pv-green);
}
.tab.amber {
  background: var(--pv-amber);
}
.tab.dim {
  background: var(--pv-line);
}
.strip.selected .tab {
  background: var(--pv-accent);
}

.body {
  display: flex;
  align-items: center;
  flex: 1;
}
.field {
  padding: 14px 16px;
  border-right: 1px solid var(--pv-line);
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.field:last-child {
  border-right: none;
}
.field.trailing {
  margin-left: auto;
  border-right: none;
  justify-content: center;
}
.fl {
  font-size: 10px;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
}
.val {
  font-family: var(--pv-font-mono);
  font-size: 13px;
  font-weight: 500;
  color: var(--pv-ink);
  white-space: nowrap;
}
.val.callsign {
  font-family: var(--pv-font-mono);
  font-size: 15px;
  font-weight: 700;
}
.val.route {
  display: flex;
  align-items: center;
  gap: 6px;
}
.val.route .arrow {
  color: var(--pv-ink-dim);
  font-size: 10px;
}
</style>
