<script setup lang="ts">
import { useZulu } from "./useZulu";

/** Station ident and live Zulu time used by the application header. */
withDefaults(defineProps<{ station?: string | null }>(), { station: null });
const { zulu } = useZulu();
</script>

<template>
  <div class="clock pv-zulu-clock" :aria-label="`Station ${station ?? 'unknown'}, ${zulu}`">
    <svg class="glyph" viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="12" r="10" />
      <polyline points="12 6 12 12 16 14" />
    </svg>
    <span v-if="station" class="ident">{{ station }}</span>
    <span v-if="station" class="sep">·</span>
    <span class="zulu">{{ zulu }}</span>
  </div>
</template>

<style scoped>
@layer components {
  .clock {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--pv-font-mono);
    font-size: 12px;
    color: var(--pv-ink-dim);
  }
  .glyph {
    width: 14px;
    height: 14px;
    stroke: var(--pv-ink-dim);
    stroke-width: 1.5;
    fill: none;
  }
  .ident {
    color: var(--pv-cyan);
    font-weight: 500;
    letter-spacing: 0.08em;
  }
  .sep {
    color: var(--pv-line);
  }
  .zulu {
    color: var(--pv-ink);
    letter-spacing: 0.06em;
  }
}
</style>
