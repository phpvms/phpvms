<script setup lang="ts">
import { computed } from "vue";

/**
 * A single EFIS readout tile: mono uppercase label over a large value. `mono`
 * renders the value in the data face (times/codes); `small` shrinks it.
 * `accent` tints the value + a top rule (mag/cyan/green/amber); default = ink.
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

const accentVar = computed(() =>
  props.accent === "ink" ? "var(--pv-ink)" : `var(--pv-${props.accent})`,
);
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
  border-radius: var(--pv-radius-md);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  box-shadow: var(--pv-shadow-panel);
  overflow: hidden;
}
/* Colored top rule for accented tiles. */
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
  font-family: var(--pv-font-mono);
  font-size: calc(8px * var(--pv-type-scale));
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
}
.value {
  font-family: var(--pv-font-display);
  font-size: calc(28px * var(--pv-type-scale));
  font-weight: 700;
  color: var(--tile-accent, var(--pv-ink));
  line-height: 1;
  letter-spacing: -0.02em;
}
.value.mono {
  font-family: var(--pv-font-mono);
  font-size: calc(22px * var(--pv-type-scale));
  font-weight: 500;
}
.value.small {
  font-size: calc(20px * var(--pv-type-scale));
}
</style>
