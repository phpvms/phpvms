<script setup lang="ts">
import type { DutyState } from "./headerTypes";

defineProps<{ duty: DutyState }>();
</script>

<template>
  <span class="pv-header-duty" :class="`is-${duty.color}`">
    <span class="dot" aria-hidden="true" />
    <span>{{ duty.label }}</span>
  </span>
</template>

<style scoped>
@layer components {
  .pv-header-duty {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--pv-ink);
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
  }
  .dot {
    width: 7px;
    height: 7px;
    border-radius: var(--pv-radius-full);
    background: var(--pv-ink-faint);
  }
  .is-success .dot {
    background: var(--pv-green);
  }
  .is-warning .dot {
    background: var(--pv-amber);
  }
}
</style>
