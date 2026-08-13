<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{ pirep: App.Http.Data.PirepData }>();
const details = computed(() =>
  [
    { label: "Airline", value: props.pirep.airline },
    { label: "Flight Type", value: props.pirep.flightType },
    { label: "Status", value: props.pirep.status },
    {
      label: "Source",
      value: props.pirep.sourceName
        ? `${props.pirep.source} · ${props.pirep.sourceName}`
        : props.pirep.source,
    },
    { label: "Route", value: props.pirep.route },
    { label: "Planned Time", value: props.pirep.plannedFlightTime },
    { label: "Planned Distance", value: props.pirep.plannedDistance },
    { label: "Block Fuel", value: props.pirep.blockFuel },
  ].filter((detail) => detail.value),
);
</script>

<template>
  <aside class="side pv-pirep-details">
    <div class="panel">
      <p class="pv-eyebrow">FLIGHT DETAILS</p>
      <dl class="kv">
        <template v-for="detail in details" :key="detail.label">
          <dt>{{ detail.label }}</dt>
          <dd>{{ detail.value }}</dd>
        </template>
      </dl>
    </div>
  </aside>
</template>

<style scoped>
@layer components {
  .side {
    display: grid;
    gap: 12px;
    align-content: start;
  }
  .panel {
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-md);
    background: var(--pv-panel);
    padding: 12px 14px;
  }
  .kv {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 6px 14px;
    margin: 8px 0 0;
    font-size: 12px;
  }
  .kv dt {
    color: var(--pv-ink-faint);
    white-space: nowrap;
  }
  .kv dd {
    margin: 0;
    text-align: right;
    color: var(--pv-ink);
  }
}
</style>
