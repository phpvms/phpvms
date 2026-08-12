<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import { nextTick, shallowRef, useTemplateRef } from "vue";
import DispatchFilters from "@/features/flights/DispatchFilters.vue";
import FlightBidDrawer from "@/features/flights/FlightBidDrawer.vue";
import FlightManifest from "@/features/flights/FlightManifest.vue";
import type { FlightFilterOptions, FlightFilters, FlightPage } from "@/features/flights/types";

const props = defineProps<{
  flights: App.Http.Data.FlightListItemData[];
  policy: App.Http.Data.FlightDispatchPolicyData;
  page: FlightPage;
  filters: FlightFilters;
  filterOptions: FlightFilterOptions;
}>();

const drawer = useTemplateRef<InstanceType<typeof FlightBidDrawer>>("drawer");
const invokingControl = shallowRef<HTMLElement | null>(null);
const searchLoading = shallowRef(false);
const searchError = shallowRef<string | null>(null);

const queryNames: Record<keyof FlightFilters, string> = {
  airlineId: "airline_id",
  flightNumber: "flight_number",
  flightType: "flight_type",
  routeCode: "route_code",
  depIcao: "dep_icao",
  arrIcao: "arr_icao",
  distanceGreaterThan: "dgt",
  distanceLessThan: "dlt",
  timeGreaterThan: "tgt",
  timeLessThan: "tlt",
  subfleetId: "subfleet_id",
  typeRatingId: "type_rating_id",
  icaoType: "icao_type",
  search: "search",
  orderBy: "orderBy",
  sortedBy: "sortedBy",
  limit: "limit",
};

function queryFor(filters: FlightFilters, page?: number): Record<string, string | number> {
  const query: Record<string, string | number> = {};
  for (const key of Object.keys(filters) as Array<keyof FlightFilters>) {
    const value = filters[key];
    if (value !== null && value !== "") query[queryNames[key]] = value;
  }
  if (page) query.page = page;
  return query;
}

function search(filters: FlightFilters) {
  router.get("/flights", queryFor(filters), {
    preserveScroll: true,
    onStart: () => {
      searchLoading.value = true;
      searchError.value = null;
    },
    onError: () => {
      searchError.value =
        "The schedule could not be updated. The current results are still available.";
    },
    onFinish: () => {
      searchLoading.value = false;
    },
  });
}

function reset() {
  router.get("/flights", {}, { preserveScroll: true });
}

function pageHref(page: number): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(queryFor(props.filters, page)))
    params.set(key, String(value));
  return `/flights?${params.toString()}`;
}

function openBid(flightId: string, event: MouseEvent) {
  invokingControl.value = event.currentTarget as HTMLElement;
  drawer.value?.show(flightId);
}

async function returnFocus() {
  await nextTick();
  invokingControl.value?.focus();
}
</script>

<template>
  <section class="pv-flights-page" aria-label="Flight schedule">
    <DispatchFilters
      :filters="filters"
      :options="filterOptions"
      :loading="searchLoading"
      :error="searchError"
      @submit="search"
      @reset="reset"
    />
    <FlightManifest :flights="flights" :policy="policy" @bid="openBid" />

    <nav v-if="page.last > 1" class="pager" aria-label="Flight results pages">
      <Link v-if="page.current > 1" :href="pageHref(page.current - 1)" preserve-scroll
        >Previous</Link
      >
      <span v-else aria-disabled="true">Previous</span>
      <p>Page {{ page.current }} of {{ page.last }} · {{ page.total }} flights</p>
      <Link v-if="page.current < page.last" :href="pageHref(page.current + 1)" preserve-scroll
        >Next</Link
      >
      <span v-else aria-disabled="true">Next</span>
    </nav>

    <FlightBidDrawer ref="drawer" @closed="returnFocus" />
  </section>
</template>

<style scoped>
.pv-flights-page {
  min-width: 0;
}
.pager {
  display: grid;
  grid-template-columns: 90px minmax(0, 1fr) 90px;
  align-items: center;
  gap: 12px;
  margin-top: 18px;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel);
  padding: 12px 16px;
  text-align: center;
}
.pager a,
.pager span {
  color: var(--pv-accent);
  font-size: calc(12px * var(--pv-type-scale));
  font-weight: 650;
  text-decoration: none;
}
.pager a:first-child,
.pager span:first-child {
  text-align: left;
}
.pager a:last-child,
.pager span:last-child {
  text-align: right;
}
.pager span[aria-disabled="true"] {
  color: var(--pv-ink-faint);
}
.pager p {
  margin: 0;
  color: var(--pv-ink-dim);
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
}
@media (max-width: 500px) {
  .pager {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .pager p {
    grid-column: 1 / -1;
    grid-row: 1;
  }
}
</style>
