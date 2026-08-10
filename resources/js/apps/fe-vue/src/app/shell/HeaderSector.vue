<script setup lang="ts">
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import type { ActiveSector } from "./headerTypes";

const props = defineProps<{ sector: ActiveSector | null; compact?: boolean }>();
const route = computed(() =>
  props.sector ? `${props.sector.departureIcao} → ${props.sector.arrivalIcao}` : "No active sector",
);
</script>

<template>
  <Link
    v-if="sector"
    :href="`/pireps/${sector.pirepId}`"
    class="pv-header-sector"
    :title="`${sector.ident} ${route}`"
  >
    <span class="label">{{ compact ? sector.ident : "Active sector" }}</span>
    <span class="route">{{ route }}</span>
  </Link>
  <span v-else class="pv-header-sector is-empty">
    <span class="label">{{ compact ? "Sector" : "Active sector" }}</span>
    <span class="route">{{ route }}</span>
  </span>
</template>

<style scoped>
@layer components {
  .pv-header-sector {
    display: grid;
    min-width: 0;
    color: var(--pv-ink);
    text-decoration: none;
  }
  a.pv-header-sector:hover .route {
    color: var(--pv-accent);
  }
  .label {
    color: var(--pv-ink-dim);
    font-size: 10px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }
  .route {
    overflow: hidden;
    font-family: var(--pv-font-mono);
    font-size: 12px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .is-empty .route {
    color: var(--pv-ink-faint);
    font-family: var(--pv-font-body);
    font-weight: 500;
  }
  .pv-header-sector:focus-visible {
    border-radius: var(--pv-radius-sm);
    outline: 2px solid var(--pv-accent);
    outline-offset: 3px;
  }
}
</style>
