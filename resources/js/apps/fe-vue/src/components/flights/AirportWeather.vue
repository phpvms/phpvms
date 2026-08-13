<script setup lang="ts">
import { useMetar } from "@/app/shell/useMetar";

const props = defineProps<{
  label: string;
  station: App.Http.Data.WeatherStationData | null;
  emptyLabel?: string;
}>();

const { retry, state } = useMetar(() => props.station);
</script>

<template>
  <section class="weather-card" :aria-label="`${label} weather`">
    <header>
      <p class="pv-eyebrow">{{ label }}</p>
      <strong>{{ station?.icao ?? "—" }}</strong>
    </header>
    <p v-if="state.kind === 'missing-station'" class="weather-message">
      {{ emptyLabel ?? "Weather station unavailable" }}
    </p>
    <p
      v-else-if="state.kind === 'loading'"
      class="weather-message"
      role="status"
      aria-live="polite"
    >
      Loading current report…
    </p>
    <template v-else-if="state.kind === 'loaded'">
      <p class="metar">{{ state.weather.metar }}</p>
      <p class="observed">Observed {{ state.weather.observedAt ?? "time unavailable" }}</p>
    </template>
    <p v-else-if="state.kind === 'missing'" class="weather-message" role="status">
      No current report for {{ state.station }}.
    </p>
    <template v-else-if="state.kind === 'stale'">
      <p class="weather-warning" role="status">Stale report</p>
      <p class="metar">{{ state.weather.metar }}</p>
      <p class="observed">Observed {{ state.weather.observedAt ?? "time unavailable" }}</p>
    </template>
    <div v-else class="weather-error" role="alert">
      <p>Weather provider error for {{ state.station }}.</p>
      <UButton type="button" size="xs" color="neutral" variant="soft" @click="retry">Retry</UButton>
    </div>
  </section>
</template>

<style scoped>
.weather-card {
  min-width: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel);
  padding: 16px;
}
header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
header strong {
  color: var(--pv-cyan);
  font-family: var(--pv-font-mono);
}
.metar {
  overflow-wrap: anywhere;
  margin: 14px 0 6px;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
  line-height: 1.6;
}
.observed,
.weather-message,
.weather-error {
  color: var(--pv-ink-dim);
  font-size: calc(11px * var(--pv-type-scale));
}
.weather-warning {
  display: inline-flex;
  margin: 12px 0 0;
  border-radius: var(--pv-radius-full);
  padding: 3px 8px;
  background: color-mix(in srgb, var(--pv-amber) 13%, transparent);
  color: var(--pv-amber);
  font-size: calc(10px * var(--pv-type-scale));
  font-weight: 700;
  text-transform: uppercase;
}
.weather-error {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
</style>
