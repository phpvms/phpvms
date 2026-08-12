<script setup lang="ts">
import NavDisplay from "@/widgets/nav-display/NavDisplay.vue";

defineProps<{ flight: App.Http.Data.FlightDetailData; compact?: boolean }>();
</script>

<template>
  <div class="flight-map" :class="{ compact }">
    <NavDisplay
      v-if="flight.departure?.lat != null && flight.departure.lon != null"
      :from="[flight.departure.lon, flight.departure.lat]"
      :to="
        flight.arrival?.lat != null && flight.arrival.lon != null
          ? [flight.arrival.lon, flight.arrival.lat]
          : null
      "
      :from-icao="flight.departure.icao"
      :to-icao="flight.arrival?.icao"
      :fl="flight.cruiseLevel == null ? undefined : `FL${flight.cruiseLevel}`"
    />
    <div v-else class="map-empty" role="status">
      Route map unavailable because airport coordinates are missing.
    </div>
  </div>
</template>

<style scoped>
.flight-map {
  min-width: 0;
}
.flight-map :deep(.map) {
  height: 360px;
}
.flight-map.compact :deep(.map) {
  height: 240px;
}
.map-empty {
  display: grid;
  min-height: 180px;
  place-items: center;
  border: 1px dashed var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  color: var(--pv-ink-dim);
  padding: 24px;
  text-align: center;
}
@media (max-width: 540px) {
  .flight-map :deep(.map),
  .flight-map.compact :deep(.map) {
    height: 220px;
  }
  .flight-map :deep(.readout) {
    gap: 6px;
    overflow-x: auto;
    font-size: calc(7px * var(--pv-type-scale));
  }
}
</style>
