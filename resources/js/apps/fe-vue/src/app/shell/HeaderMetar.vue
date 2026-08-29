<script setup lang="ts">
import { computed } from "vue";
import type { MetarState } from "./useMetar";
import UButton from "@nuxt/ui/components/Button.vue";

const props = defineProps<{ state: MetarState }>();
const emit = defineEmits<{ retry: [] }>();
const observedAt = computed(() => {
  if (props.state.kind !== "stale" || !props.state.weather.observedAt) return null;
  return new Date(props.state.weather.observedAt).toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });
});
</script>

<template>
  <div class="pv-header-metar" aria-label="METAR">
    <span class="label">METAR</span>
    <span v-if="state.kind === 'loading'" class="value loading">Loading METAR…</span>
    <span v-else-if="state.kind === 'missing-station'" class="value">No station</span>
    <span v-else-if="state.kind === 'missing'" class="value">No METAR</span>
    <template v-else-if="state.kind === 'error'">
      <span class="value">METAR unavailable</span>
      <UButton
        class="pv-header-metar-retry"
        size="xs"
        color="neutral"
        variant="link"
        @click="emit('retry')"
        >Retry</UButton
      >
    </template>
    <template v-else>
      <span class="value raw">{{ state.weather.metar }}</span>
      <time
        v-if="state.kind === 'stale' && observedAt"
        class="stale"
        :datetime="state.weather.observedAt ?? undefined"
        >Stale · {{ observedAt }}</time
      >
    </template>
  </div>
</template>

<style scoped>
@layer components {
  .pv-header-metar {
    display: grid;
    min-width: 0;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    column-gap: 7px;
  }
  .label {
    color: var(--pv-cyan);
    font-family: var(--pv-font-mono);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
  }
  .value {
    min-width: 0;
    overflow: hidden;
    color: var(--pv-ink-dim);
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .raw {
    color: var(--pv-ink);
    font-family: var(--pv-font-mono);
  }
  .loading {
    color: var(--pv-ink-faint);
  }
  .stale {
    grid-column: 2;
    color: var(--pv-amber);
    font-size: 10px;
  }
  .pv-header-metar-retry {
    justify-self: start;
    min-height: auto;
    padding: 0;
  }
  .pv-header-metar-retry:focus-visible {
    outline: 2px solid var(--pv-accent);
    outline-offset: 2px;
  }
}
</style>
