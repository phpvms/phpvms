<script setup lang="ts">
import { computed } from "vue";
import { router, Link } from "@inertiajs/vue3";
import PvApp from "@/app/PvApp.vue";
import FmsEntry from "@/widgets/flight/FmsEntry.vue";
import FlightStrip from "@/widgets/flight/FlightStrip.vue";

/**
 * Flights schedule page. Reads the flat FlightsPresenter DTO: a page of flights,
 * pagination meta, and the active filters. Filtering + paging are real Inertia
 * visits (server re-runs the search), so this page stays a thin projection.
 */
defineOptions({ layout: PvApp });

const props = defineProps<{
  flights: App.Http.Data.FlightListItemData[];
  page: { current: number; last: number; total: number };
  filters: {
    depIcao: string | null;
    arrIcao: string | null;
    flightNumber: string | null;
    flightType: string | null;
  };
}>();

function search(q: { from: string; to: string }) {
  router.get(
    "/flights",
    {
      dep_icao: q.from || undefined,
      arr_icao: q.to || undefined,
    },
    { preserveState: false, preserveScroll: true },
  );
}

function fieldsFor(f: App.Http.Data.FlightListItemData) {
  return [
    { label: "Dist", value: f.distanceNm != null ? `${f.distanceNm}NM` : "—" },
    { label: "Block", value: f.blockTime ?? "—" },
    { label: "Type", value: f.type ?? "—" },
  ];
}

function pageHref(n: number): string {
  const p = new URLSearchParams();
  if (props.filters.depIcao) p.set("dep_icao", props.filters.depIcao);
  if (props.filters.arrIcao) p.set("arr_icao", props.filters.arrIcao);
  p.set("page", String(n));
  return `/flights?${p.toString()}`;
}

const hasPrev = computed(() => props.page.current > 1);
const hasNext = computed(() => props.page.current < props.page.last);
</script>

<template>
  <section aria-label="Flight schedule">
    <p class="pv-eyebrow">FLIGHTS · SCHEDULE</p>
    <FmsEntry :from="filters.depIcao" @search="search" />

    <div class="board" role="list">
      <FlightStrip
        v-for="f in flights"
        :key="f.id"
        role="listitem"
        :callsign="f.callsign"
        :dpt="f.dpt"
        :arr="f.arr"
        :fields="fieldsFor(f)"
        :tab="f.bidId ? 'green' : 'dim'"
      >
        <template #trailing>
          <span class="badge" :class="f.bidId ? 'on' : 'avail'">
            {{ f.bidId ? "On Bid" : "Available" }}
          </span>
        </template>
      </FlightStrip>

      <div v-if="!flights.length" class="empty">No flights match your filters</div>
    </div>

    <!-- Pagination -->
    <div v-if="page.last > 1" class="pager">
      <Link v-if="hasPrev" :href="pageHref(page.current - 1)" class="pg" preserve-scroll
        >← Prev</Link
      >
      <span v-else class="pg disabled">← Prev</span>
      <span class="pg-info"
        >Page {{ page.current }} of {{ page.last }} · {{ page.total }} flights</span
      >
      <Link v-if="hasNext" :href="pageHref(page.current + 1)" class="pg" preserve-scroll
        >Next →</Link
      >
      <span v-else class="pg disabled">Next →</span>
    </div>
  </section>
</template>

<style scoped>
.board {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 10px;
}
.empty {
  font-size: 13px;
  color: var(--pv-ink-dim);
  border: 1px dashed var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 24px;
  text-align: center;
}
.badge {
  font-size: 11px;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: var(--pv-radius-full);
}
.badge.on {
  color: var(--pv-green);
  background: color-mix(in srgb, var(--pv-green) 8%, transparent);
}
.badge.avail {
  color: var(--pv-ink-dim);
  background: var(--pv-panel-inset);
}
.pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 16px;
}
.pg {
  font-size: 12px;
  color: var(--pv-accent);
  text-decoration: none;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-sm);
  padding: 6px 12px;
}
.pg:hover {
  border-color: var(--pv-accent);
}
.pg.disabled {
  color: var(--pv-ink-dim);
  opacity: 0.5;
}
.pg-info {
  font-size: 12px;
  color: var(--pv-ink-dim);
  font-variant-numeric: tabular-nums;
}
</style>
