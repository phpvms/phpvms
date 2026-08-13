<script setup lang="ts">
defineProps<{
  aircraft: App.Http.Data.EligibleAircraftData | null;
  label: string;
}>();
</script>

<template>
  <article class="aircraft-card" :aria-label="label">
    <header class="aircraft-card__header">
      <span class="aircraft-card__label">{{ label }}</span>
      <slot name="action" />
    </header>
    <template v-if="aircraft">
      <strong>{{ aircraft.registration }} · {{ aircraft.icaoType }}</strong>
      <span>{{ aircraft.subfleetName }} · {{ aircraft.airport?.icao ?? "" }}</span>
    </template>
    <strong v-else>Aircraft not selected</strong>
  </article>
</template>

<style scoped>
.aircraft-card {
  display: grid;
  min-width: 0;
  gap: 4px;
  border: 1px solid var(--pv-line-strong);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  padding: 12px 14px;
}
.aircraft-card__label {
  color: var(--pv-ink-dim);
  font-size: calc(0.6rem * var(--pv-type-scale));
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.aircraft-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.aircraft-card strong {
  overflow-wrap: anywhere;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.aircraft-card > span:last-child:not(.aircraft-card__label) {
  overflow-wrap: anywhere;
  color: var(--pv-ink-dim);
  font-size: calc(0.75rem * var(--pv-type-scale));
}
</style>
