<script setup lang="ts">
import { computed } from "vue";
import { formatPirepDate } from "./formatPirepDate";

const props = defineProps<{ pirep: App.Http.Data.PirepData }>();
const stats = computed(() => [
  { label: "Flight Time", value: props.pirep.flightTime ?? "—" },
  { label: "Distance", value: props.pirep.distance ?? "—" },
  { label: "Score", value: props.pirep.score != null ? String(props.pirep.score) : "—" },
  {
    label: "Landing Rate",
    value: props.pirep.landingRate != null ? `${Math.round(props.pirep.landingRate)} fpm` : "—",
  },
  { label: "Fuel Used", value: props.pirep.fuelUsed ?? "—" },
  { label: "Cruise", value: props.pirep.cruise ?? "—" },
]);
</script>

<template>
  <div class="pv-pirep-summary">
    <header class="hero">
      <div class="hl">
        <h1 class="ident">{{ pirep.ident }}</h1>
        <div class="sub">{{ pirep.aircraft ?? "—" }}</div>
        <div class="sub2">
          <template v-if="pirep.pilotName">
            {{ pirep.pilotName }}<span v-if="pirep.pilotRank"> · {{ pirep.pilotRank }}</span> ·
          </template>
          Filed {{ formatPirepDate(pirep.submittedAt) }}
        </div>
      </div>
      <span class="badge big" :data-c="pirep.stateColor">{{ pirep.state }}</span>
    </header>

    <div class="route">
      <div class="ap">
        <span class="icao">{{ pirep.dpt }}</span>
        <span class="apname">{{ pirep.dptName ?? "" }}</span>
      </div>
      <span class="arrow" aria-hidden="true">→</span>
      <div class="ap ar">
        <span class="icao">{{ pirep.arr }}</span>
        <span class="apname">{{ pirep.arrName ?? "" }}</span>
      </div>
    </div>

    <div class="strip">
      <div v-for="stat in stats" :key="stat.label" class="cell">
        <span class="k">{{ stat.label }}</span>
        <span class="v">{{ stat.value }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
@layer components {
  .hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-top: 8px;
  }
  .ident {
    font-family: var(--pv-font-mono);
    font-weight: 600;
    color: var(--pv-accent);
    font-size: 20px;
    margin: 0;
  }
  .sub {
    font-size: 13px;
    color: var(--pv-ink);
    margin-top: 2px;
  }
  .sub2 {
    font-size: 11px;
    color: var(--pv-ink-dim);
    margin-top: 2px;
  }
  .route {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 14px;
    padding: 12px 14px;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-md);
    background: var(--pv-panel);
  }
  .ap {
    display: flex;
    flex-direction: column;
  }
  .ap.ar {
    text-align: right;
    margin-left: auto;
  }
  .icao {
    font-family: var(--pv-font-mono);
    font-weight: 600;
    font-size: 15px;
  }
  .apname {
    font-size: 11px;
    color: var(--pv-ink-dim);
  }
  .arrow {
    color: var(--pv-ink-faint);
  }
  .strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
    gap: 1px;
    margin-top: 12px;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-md);
    overflow: hidden;
    background: var(--pv-line);
  }
  .cell {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 10px 12px;
    background: var(--pv-panel);
  }
  .cell .k {
    font-size: 10px;
    text-transform: uppercase;
    color: var(--pv-ink-faint);
  }
  .cell .v {
    font-size: 13px;
    font-variant-numeric: tabular-nums;
  }
  .badge {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: var(--pv-radius-full);
    white-space: nowrap;
  }
  .badge.big {
    font-size: 13px;
    padding: 5px 12px;
  }
  .badge[data-c="success"] {
    color: var(--pv-green);
    background: color-mix(in srgb, var(--pv-green) 12%, transparent);
  }
  .badge[data-c="warning"] {
    color: var(--pv-amber);
    background: color-mix(in srgb, var(--pv-amber) 12%, transparent);
  }
  .badge[data-c="danger"] {
    color: var(--pv-red);
    background: color-mix(in srgb, var(--pv-red) 12%, transparent);
  }
  .badge[data-c="info"] {
    color: var(--pv-accent);
    background: var(--pv-accent-soft);
  }
  .badge[data-c="gray"] {
    color: var(--pv-ink-dim);
    background: color-mix(in srgb, var(--pv-ink-dim) 12%, transparent);
  }
}
</style>
