<script setup lang="ts">
import { computed, reactive, watch } from "vue";
import type { FlightFilterOptions, FlightFilters } from "./types";
import IconSearch from "~icons/tabler/search";
import UButton from "@nuxt/ui/components/Button.vue";
import UInput from "@nuxt/ui/components/Input.vue";
import USelect from "@nuxt/ui/components/Select.vue";

const props = defineProps<{
  filters: FlightFilters;
  options: FlightFilterOptions;
  loading?: boolean;
  error?: string | null;
}>();
const emit = defineEmits<{ reset: []; submit: [filters: FlightFilters] }>();

const form = reactive<FlightFilters>({ ...props.filters });

watch(
  () => props.filters,
  (next) => Object.assign(form, next),
  { deep: true },
);

const optionItems = computed(() => ({
  airlines: Object.entries(props.options.airlines)
    .filter(([value]) => value !== "")
    .map(([value, label]) => ({
      value,
      label,
    })),
  flightTypes: Object.entries(props.options.flightTypes)
    .filter(([value]) => value !== "")
    .map(([value, label]) => ({ value, label })),
  subfleets: Object.entries(props.options.subfleets)
    .filter(([value]) => value !== "")
    .map(([value, label]) => ({
      value,
      label,
    })),
  typeRatings: props.options.typeRatings.map((rating) => ({
    value: String(rating.id),
    label: `${rating.type} · ${rating.name}`,
  })),
  icaoTypes: props.options.icaoTypes.map((value) => ({ value, label: value })),
  sort: [
    { value: "dpt_time", label: "Departure time" },
    { value: "arr_time", label: "Arrival time" },
    { value: "distance", label: "Distance" },
    { value: "flight_number", label: "Flight number" },
  ],
  direction: [
    { value: "asc", label: "Ascending" },
    { value: "desc", label: "Descending" },
  ],
}));

const activeFilters = computed(() => {
  const labels: Record<keyof FlightFilters, string> = {
    airlineId: "Airline",
    flightNumber: "Flight",
    flightType: "Type",
    routeCode: "Route code",
    depIcao: "Departure",
    arrIcao: "Arrival",
    distanceGreaterThan: "Min distance",
    distanceLessThan: "Max distance",
    timeGreaterThan: "Min time",
    timeLessThan: "Max time",
    subfleetId: "Subfleet",
    typeRatingId: "Type rating",
    icaoType: "ICAO type",
    search: "Search",
    orderBy: "Sort",
    sortedBy: "Direction",
    limit: "Page size",
  };

  return (Object.keys(form) as Array<keyof FlightFilters>)
    .filter((key) => form[key] !== null && form[key] !== "")
    .map((key) => `${labels[key]}: ${form[key]}`);
});

function clear() {
  for (const key of Object.keys(form) as Array<keyof FlightFilters>) form[key] = null;
  emit("reset");
}
</script>

<template>
  <section class="pv-dispatch-filters" aria-labelledby="flight-filter-heading">
    <div class="filter-heading">
      <div>
        <p class="pv-eyebrow">SCHEDULE MANIFEST</p>
        <h1 id="flight-filter-heading">Find a flight</h1>
      </div>
      <span class="filter-count">{{ activeFilters.length }} active</span>
    </div>

    <div v-if="activeFilters.length" class="active-filters" aria-label="Active filters">
      <span v-for="filter in activeFilters" :key="filter">{{ filter }}</span>
    </div>
    <div v-if="error" class="filter-error" role="alert">{{ error }}</div>

    <details class="filter-disclosure" :open="activeFilters.length > 0">
      <summary>Filters</summary>
      <form class="filter-grid" @submit.prevent="emit('submit', { ...form })">
        <label
          ><span>Airline</span
          ><USelect
            v-model.nullable="form.airlineId"
            :items="optionItems.airlines"
            placeholder="All airlines"
        /></label>
        <label
          ><span>Flight type</span
          ><USelect
            v-model.nullable="form.flightType"
            :items="optionItems.flightTypes"
            placeholder="All types"
        /></label>
        <label
          ><span>Flight number</span
          ><UInput v-model.nullable="form.flightNumber" autocomplete="off" placeholder="104"
        /></label>
        <label
          ><span>Route code</span
          ><UInput v-model.nullable="form.routeCode" autocomplete="off" placeholder="A"
        /></label>
        <label
          ><span>Departure</span
          ><UInput
            v-model.nullable="form.depIcao"
            autocomplete="off"
            maxlength="8"
            placeholder="KDFW"
        /></label>
        <label
          ><span>Arrival</span
          ><UInput
            v-model.nullable="form.arrIcao"
            autocomplete="off"
            maxlength="8"
            placeholder="KORD"
        /></label>
        <label
          ><span>Subfleet</span
          ><USelect
            v-model.nullable="form.subfleetId"
            :items="optionItems.subfleets"
            placeholder="All subfleets"
        /></label>
        <label
          ><span>Type rating</span
          ><USelect
            v-model.nullable="form.typeRatingId"
            :items="optionItems.typeRatings"
            placeholder="All ratings"
        /></label>
        <label
          ><span>ICAO type</span
          ><USelect
            v-model.nullable="form.icaoType"
            :items="optionItems.icaoTypes"
            placeholder="All aircraft"
        /></label>
        <label
          ><span>Free text</span
          ><UInput
            v-model.nullable="form.search"
            autocomplete="off"
            placeholder="Airport, route, callsign"
        /></label>
        <label
          ><span>Minimum distance</span
          ><UInput v-model.nullable="form.distanceGreaterThan" inputmode="numeric" placeholder="0"
        /></label>
        <label
          ><span>Maximum distance</span
          ><UInput v-model.nullable="form.distanceLessThan" inputmode="numeric" placeholder="Any"
        /></label>
        <label
          ><span>Minimum time</span
          ><UInput
            v-model.nullable="form.timeGreaterThan"
            inputmode="numeric"
            placeholder="Minutes"
        /></label>
        <label
          ><span>Maximum time</span
          ><UInput v-model.nullable="form.timeLessThan" inputmode="numeric" placeholder="Minutes"
        /></label>
        <label
          ><span>Sort by</span
          ><USelect v-model.nullable="form.orderBy" :items="optionItems.sort" placeholder="Default"
        /></label>
        <label
          ><span>Direction</span
          ><USelect
            v-model.nullable="form.sortedBy"
            :items="optionItems.direction"
            placeholder="Default"
        /></label>
        <label
          ><span>Page size</span
          ><UInput v-model.nullable="form.limit" inputmode="numeric" placeholder="25"
        /></label>

        <div class="filter-actions">
          <UButton type="submit" :icon="IconSearch" :loading="loading" :disabled="loading">{{
            loading ? "Updating results" : "Apply filters"
          }}</UButton>
          <UButton type="button" color="neutral" variant="ghost" :disabled="loading" @click="clear"
            >Clear</UButton
          >
        </div>
      </form>
    </details>
  </section>
</template>

<style scoped>
.pv-dispatch-filters {
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-xl);
  background: var(--pv-panel);
  box-shadow: var(--pv-shadow-panel);
  padding: 20px;
}
.filter-heading,
.filter-actions,
.active-filters {
  display: flex;
  align-items: center;
}
.filter-error {
  margin-top: 14px;
  border: 1px solid color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
  border-radius: var(--pv-radius-md);
  background: color-mix(in srgb, var(--pv-red) 8%, var(--pv-panel));
  color: var(--pv-red);
  padding: 10px 12px;
  font-size: calc(12px * var(--pv-type-scale));
}
.filter-heading {
  justify-content: space-between;
  gap: 16px;
}
h1 {
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-size: calc(24px * var(--pv-type-scale));
}
.filter-count,
.active-filters span {
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-full);
  background: var(--pv-panel-inset);
  color: var(--pv-ink-dim);
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
  padding: 4px 9px;
}
.active-filters {
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 14px;
}
.filter-disclosure {
  margin-top: 16px;
}
.filter-disclosure summary {
  cursor: pointer;
  color: var(--pv-accent);
  font-weight: 650;
}
.filter-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-top: 16px;
}
label {
  display: grid;
  min-width: 0;
  gap: 6px;
}
label > span {
  color: var(--pv-ink-dim);
  font-size: calc(11px * var(--pv-type-scale));
  font-weight: 650;
}
.filter-actions {
  grid-column: 1 / -1;
  justify-content: flex-end;
  gap: 8px;
}
@media (max-width: 900px) {
  .filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 540px) {
  .pv-dispatch-filters {
    padding: 16px;
  }
  .filter-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .filter-actions {
    justify-content: stretch;
  }
  .filter-actions :deep(button) {
    flex: 1;
  }
}
</style>
