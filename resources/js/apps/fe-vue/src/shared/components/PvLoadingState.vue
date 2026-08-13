<script setup lang="ts">
defineProps<{
  text?: string;
}>();
</script>

<template>
  <div class="pv-loading-state" role="status" aria-live="polite" :aria-label="text ?? 'Loading'">
    <span class="pv-loading-state__spinner" aria-hidden="true" />
    <span v-if="text" class="pv-loading-state__text">{{ text }}</span>
  </div>
</template>

<style scoped>
.pv-loading-state {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}

.pv-loading-state__spinner {
  flex: 0 0 auto;
  width: 20px;
  height: 20px;
  border: 2px solid var(--pv-line-strong);
  border-top-color: var(--pv-accent);
  border-radius: 50%;
  animation: pv-loading-spin 0.8s linear infinite;
}

.pv-loading-state__text {
  min-width: 0;
}

@keyframes pv-loading-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .pv-loading-state__spinner {
    animation: none;
  }
}
</style>
