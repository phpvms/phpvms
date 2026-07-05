<script setup lang="ts">
import { computed } from "vue";

/**
 * Workspace stat tile: small Inter label over a large value. `mono` renders the
 * value in the data face (times/codes); `small` shrinks it.
 * `accent` tints the value + a top rule (mag/cyan/green/amber); default = ink.
 * `mag` maps to the brand-blue accent (`--pv-accent`).
 */
type Accent = "ink" | "mag" | "cyan" | "green" | "amber";
const props = withDefaults(
  defineProps<{
    label: string;
    value: string | number;
    mono?: boolean;
    small?: boolean;
    accent?: Accent;
  }>(),
  { mono: false, small: false, accent: "ink" },
);

const accentVar = computed(() => {
  if (props.accent === "ink") return "var(--pv-ink)";
  if (props.accent === "mag") return "var(--pv-accent)";
  return `var(--pv-${props.accent})`;
});
</script>

<template>
  <div class="tile" :style="{ '--tile-accent': accentVar }" :class="{ accented: accent !== 'ink' }">
    <span class="label">{{ label }}</span>
    <span class="value" :class="{ mono, small }">{{ value }}</span>
  </div>
</template>

<style scoped>
.tile {
  position: relative;
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-lg);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  overflow: hidden;
}
/* Colored top rule for accented tiles — decorative; label+value convey meaning. */
.tile.accented::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: var(--tile-accent);
}
.label {
  font-size: 11px;
  font-weight: 500;
  color: var(--pv-ink-dim);
}
.value {
  font-size: 28px;
  font-weight: 700;
  color: var(--tile-accent, var(--pv-ink));
  line-height: 1;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}
.value.mono {
  font-family: var(--pv-font-mono);
  font-size: 22px;
  font-weight: 500;
}
.value.small {
  font-size: 20px;
}
</style>
