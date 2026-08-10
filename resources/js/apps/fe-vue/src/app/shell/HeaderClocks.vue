<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{ utc: string; local: string | null; timezone: string | null }>();
const utcDatetime = computed(() => props.utc.replace("Z", ""));
</script>

<template>
  <div class="pv-header-clocks">
    <span class="clock"
      ><span class="label">UTC</span><time :datetime="utcDatetime">{{ utc }}</time></span
    >
    <span class="clock local"
      ><span class="label">Local</span
      ><time :datetime="local ?? undefined">{{ local ?? "Unavailable" }}</time></span
    >
  </div>
</template>

<style scoped>
@layer components {
  .pv-header-clocks {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: var(--pv-font-mono);
  }
  .clock {
    display: grid;
    gap: 1px;
    white-space: nowrap;
  }
  .label {
    color: var(--pv-ink-faint);
    font-family: var(--pv-font-body);
    font-size: 10px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }
  time {
    color: var(--pv-ink);
    font-size: 12px;
    font-variant-numeric: tabular-nums;
  }
  .local time {
    color: var(--pv-ink-dim);
  }
}
</style>
