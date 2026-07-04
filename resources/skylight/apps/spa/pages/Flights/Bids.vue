<script setup lang="ts">
import PvApp from "@/components/pv/PvApp.vue";
import PvSlot from "@/components/pv/PvSlot.vue";

/**
 * My Bids page. Reads BidRowData[] (one row per validated bid: a `bid` object +
 * its `flight` summary). The row types are GENERATED from the PHP DTOs by
 * `php artisan typescript:transform` (App.Http.Data.* — an ambient global, no
 * import), so client and server shapes stay in lock-step. Rendered as a semantic
 * table (Workspace look). Each row exposes a per-instance extension outlet
 * `bids.row.actions` whose `context` is `{ bid, flight }` — the host for the
 * external ACARS plugin, which injects a Vue component into the row.
 */
defineOptions({ layout: PvApp });

defineProps<{
  bids: App.Http.Data.BidRowData[];
  acarsPlugin: boolean;
}>();
</script>

<template>
  <section aria-label="My bids">
    <p class="pv-eyebrow">FLIGHTS · MY BIDS</p>

    <div v-if="bids.length" class="tablewrap">
      <table class="bids">
        <thead>
          <tr>
            <th scope="col">Flight</th>
            <th scope="col">Dep</th>
            <th scope="col">Arr</th>
            <th scope="col" class="num">Dist</th>
            <th scope="col" class="num">Block</th>
            <th scope="col">Type</th>
            <th scope="col" class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in bids" :key="row.bid.id">
            <td class="callsign">{{ row.flight?.callsign ?? "—" }}</td>
            <td>{{ row.flight?.dpt ?? "—" }}</td>
            <td>{{ row.flight?.arr ?? "—" }}</td>
            <td class="num">
              {{ row.flight?.distanceNm != null ? `${row.flight.distanceNm}NM` : "—" }}
            </td>
            <td class="num">{{ row.flight?.blockTime ?? "—" }}</td>
            <td class="type">{{ row.flight?.type ?? "—" }}</td>
            <td class="actions">
              <PvSlot name="bids.row.actions" :context="{ bid: row.bid, flight: row.flight }" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="empty">NO BIDS YET · RESERVE A FLIGHT TO GET STARTED</div>
  </section>
</template>

<style scoped>
.tablewrap {
  margin-top: 10px;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  overflow: hidden;
}
.bids {
  width: 100%;
  border-collapse: collapse;
  font-size: calc(12px * var(--pv-type-scale));
  color: var(--pv-ink);
}
.bids thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--pv-panel);
  border-bottom: 1px solid var(--pv-line);
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
  font-weight: 500;
  text-align: left;
  padding: 8px 12px;
}
.bids tbody td {
  padding: 9px 12px;
  border-bottom: 1px solid var(--pv-line);
  white-space: nowrap;
}
.bids tbody tr:last-child td {
  border-bottom: none;
}
.bids tbody tr:hover td {
  background: var(--pv-hover);
}
.callsign {
  font-family: var(--pv-font-mono);
  font-weight: 500;
  color: var(--pv-accent);
}
.type {
  color: var(--pv-ink-dim);
}
.num {
  font-variant-numeric: tabular-nums;
  text-align: right;
}
.actions {
  text-align: right;
  width: 1%;
}
.empty {
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
  letter-spacing: 0.16em;
  color: var(--pv-ink-dim);
  border: 1px dashed var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 24px;
  text-align: center;
  margin-top: 10px;
}
</style>
