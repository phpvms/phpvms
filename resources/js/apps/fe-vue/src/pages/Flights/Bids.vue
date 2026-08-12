<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed, nextTick, shallowRef, useTemplateRef } from "vue";
import FlightBidDrawer from "@/features/flights/FlightBidDrawer.vue";
import PvSlot from "@/shared/ui/PvSlot.vue";

/**
 * My Bids page. Reads BidRowData[] (one row per validated bid: a `bid` object +
 * its `flight` summary). The row types are GENERATED from the PHP DTOs by
 * `php artisan typescript:transform` (App.Http.Data.* — an ambient global, no
 * import), so client and server shapes stay in lock-step. Rendered as a semantic
 * table (Workspace look). Each row exposes a per-instance extension outlet
 * `bids.row.actions` whose `context` is `{ bid, flight }` — the host for the
 * external ACARS plugin, which injects a Vue component into the row.
 */

const props = defineProps<{
  bids: App.Http.Data.BidRowData[];
  acarsPlugin: boolean;
}>();

const drawer = useTemplateRef<InstanceType<typeof FlightBidDrawer>>("drawer");
const invokingControl = shallowRef<HTMLElement | null>(null);
const confirmingId = shallowRef<number | null>(null);
const removingId = shallowRef<number | null>(null);
const removeError = shallowRef<string | null>(null);
const removedId = shallowRef<number | null>(null);
const visibleBids = computed(() => props.bids.filter((row) => row.bid.id !== removedId.value));

function openBid(flightId: string, event: MouseEvent) {
  invokingControl.value = event.currentTarget as HTMLElement;
  drawer.value?.show(flightId);
}

async function returnFocus() {
  await nextTick();
  invokingControl.value?.focus();
}

function formatExpiry(value: string | null): string {
  if (!value) return "No expiry";
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function simBriefHref(row: App.Http.Data.BidRowData): string {
  const params = new URLSearchParams({ flight_id: row.bid.flightId });
  if (row.aircraft) params.set("aircraft_id", String(row.aircraft.id));
  return `/simbrief/planning?${params.toString()}`;
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

function requestRemoval(id: number) {
  removeError.value = null;
  confirmingId.value = id;
}

function cancelRemoval() {
  if (removingId.value === null) confirmingId.value = null;
}

async function removeBid(row: App.Http.Data.BidRowData) {
  if (!row.canRemove || removingId.value !== null) return;

  confirmingId.value = null;
  removingId.value = row.bid.id;
  removeError.value = null;

  try {
    const response = await fetch(`/flights/${encodeURIComponent(row.bid.flightId)}/bid`, {
      method: "DELETE",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
    });
    const body = (await response.json().catch(() => ({}))) as { message?: string };
    if (!response.ok) throw new Error(body.message ?? "The bid could not be removed. Try again.");
    removedId.value = row.bid.id;
  } catch (error) {
    removeError.value =
      error instanceof Error
        ? error.message
        : "The bid could not be removed. Check your connection and try again.";
  } finally {
    removingId.value = null;
  }
}
</script>

<template>
  <section class="pv-my-bids" aria-labelledby="my-bids-heading">
    <header class="page-heading">
      <div>
        <p class="pv-eyebrow">FLIGHTS · MY BIDS</p>
        <h1 id="my-bids-heading">My bids</h1>
        <p class="page-summary">Your reserved dispatches, ready to reopen and brief.</p>
      </div>
      <span class="bid-count"
        >{{ visibleBids.length }} {{ visibleBids.length === 1 ? "bid" : "bids" }}</span
      >
    </header>

    <div v-if="removeError" class="remove-error" role="alert" aria-live="assertive">
      {{ removeError }}
    </div>

    <div v-if="visibleBids.length" class="tablewrap">
      <table class="bids">
        <thead>
          <tr>
            <th scope="col">Flight</th>
            <th scope="col">Route</th>
            <th scope="col">Schedule</th>
            <th scope="col" class="num">Dist</th>
            <th scope="col" class="num">Block</th>
            <th scope="col">Aircraft</th>
            <th scope="col">Bid</th>
            <th scope="col">Expiry</th>
            <th scope="col" class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in visibleBids" :key="row.bid.id">
            <td class="callsign" data-label="Flight">
              <strong>{{ row.flight?.callsign ?? row.bid.flightId }}</strong>
              <span class="route-code">
                {{ row.flight?.routeCode ? `Route ${row.flight.routeCode}` : "" }}
                <template v-if="row.flight?.type"> · {{ row.flight.type }}</template>
              </span>
            </td>
            <td data-label="Route">
              <span class="route-pair"
                >{{ row.flight?.dpt ?? "—" }} → {{ row.flight?.arr ?? "—" }}</span
              >
            </td>
            <td data-label="Schedule">
              <span class="schedule"
                >{{ row.flight?.scheduledDeparture ?? "—" }} →
                {{ row.flight?.scheduledArrival ?? "—" }}</span
              >
            </td>
            <td class="num" data-label="Distance">
              {{ row.flight?.distanceNm != null ? `${row.flight.distanceNm}NM` : "—" }}
            </td>
            <td class="num" data-label="Block">{{ row.flight?.blockTime ?? "—" }}</td>
            <td data-label="Aircraft">
              <span v-if="row.aircraft" class="aircraft">
                <strong>{{ row.aircraft.registration }}</strong>
                <span>{{ row.aircraft.icaoType }} · {{ row.aircraft.subfleetName }}</span>
              </span>
              <span v-else class="muted">Aircraft not selected</span>
            </td>
            <td data-label="Bid">
              <span class="bid-state" :data-state="row.state">{{ row.state }}</span>
            </td>
            <td data-label="Expiry">
              <span class="expiry">{{ formatExpiry(row.expiresAt) }}</span>
            </td>
            <td class="actions" data-label="Actions">
              <div class="row-actions">
                <Link v-if="row.flight" class="detail-link" :href="`/flights/${row.bid.flightId}`"
                  >Details</Link
                >
                <Link v-if="row.canGenerateSimBrief" class="simbrief-link" :href="simBriefHref(row)"
                  >Generate SimBrief</Link
                >
                <UButton
                  type="button"
                  size="sm"
                  variant="soft"
                  @click="openBid(row.bid.flightId, $event)"
                  >Overview</UButton
                >
                <template v-if="row.canRemove">
                  <UButton
                    v-if="confirmingId !== row.bid.id"
                    type="button"
                    size="sm"
                    color="neutral"
                    variant="ghost"
                    :disabled="removingId !== null"
                    @click="requestRemoval(row.bid.id)"
                    >Remove</UButton
                  >
                  <span v-else class="remove-confirmation">
                    <span>Remove this bid?</span>
                    <UButton
                      type="button"
                      size="sm"
                      color="error"
                      :loading="removingId === row.bid.id"
                      :disabled="removingId !== null"
                      @click="removeBid(row)"
                      >Confirm removal</UButton
                    >
                    <UButton
                      type="button"
                      size="sm"
                      color="neutral"
                      variant="ghost"
                      :disabled="removingId !== null"
                      @click="cancelRemoval"
                      >Keep</UButton
                    >
                  </span>
                </template>
                <PvSlot name="bids.row.actions" :context="{ bid: row.bid, flight: row.flight }" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="empty">
      <strong>No bids yet</strong>
      <p>Reserve a flight from the manifest to keep it here for your next dispatch.</p>
      <Link class="browse-link" href="/flights">Browse flights</Link>
    </div>

    <FlightBidDrawer ref="drawer" @closed="returnFocus" />
  </section>
</template>

<style scoped>
.pv-my-bids {
  min-width: 0;
}
.page-heading {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}
.page-heading h1 {
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-size: calc(24px * var(--pv-type-scale));
}
.page-summary {
  margin: 6px 0 0;
  color: var(--pv-ink-dim);
  font-size: 1rem;
}
.bid-count {
  flex: 0 0 auto;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-full);
  background: var(--pv-panel-inset);
  color: var(--pv-ink-dim);
  font-family: var(--pv-font-mono);
  font-size: 0.75rem;
  padding: 5px 10px;
}
.tablewrap {
  min-width: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  overflow: hidden;
  background: var(--pv-panel);
}
.remove-error {
  margin-bottom: 12px;
  border: 1px solid color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
  border-radius: var(--pv-radius-md);
  background: color-mix(in srgb, var(--pv-red) 8%, var(--pv-panel));
  color: var(--pv-red);
  padding: 10px 12px;
  font-size: 0.875rem;
}
.bids {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
  color: var(--pv-ink);
}
.bids thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--pv-panel);
  border-bottom: 1px solid var(--pv-line-strong, var(--pv-line));
  font-size: 0.75rem;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
  font-weight: 500;
  text-align: left;
  padding: 10px 14px;
}
.bids tbody td {
  padding: 12px 14px;
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
  display: grid;
  gap: 3px;
  font-family: var(--pv-font-mono);
  font-weight: 500;
  color: var(--pv-accent);
}
.route-code,
.aircraft span,
.muted,
.expiry,
.schedule {
  color: var(--pv-ink-dim);
  font-size: 0.75rem;
}
.route-pair,
.schedule,
.aircraft,
.expiry {
  font-family: var(--pv-font-mono);
}
.aircraft {
  display: grid;
  gap: 3px;
}
.aircraft strong {
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
}
.bid-state {
  display: inline-flex;
  border-radius: var(--pv-radius-full);
  background: color-mix(in srgb, var(--pv-green) 13%, transparent);
  color: var(--pv-green);
  padding: 3px 8px;
  font-size: 0.75rem;
  font-weight: 750;
  text-transform: uppercase;
}
.bid-state[data-state="expired"] {
  background: color-mix(in srgb, var(--pv-amber) 13%, transparent);
  color: var(--pv-amber);
}
.num {
  font-variant-numeric: tabular-nums;
  text-align: right;
}
.actions {
  text-align: right;
  width: 1%;
}
.row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
}
.remove-confirmation {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  color: var(--pv-ink-dim);
  font-size: 0.75rem;
}
.detail-link {
  color: var(--pv-accent);
  font-size: 0.875rem;
  font-weight: 650;
  text-decoration: none;
}
.detail-link:hover {
  text-decoration: underline;
}
.simbrief-link {
  color: var(--pv-accent);
  font-size: 0.875rem;
  font-weight: 650;
  text-decoration: none;
}
.simbrief-link:hover {
  text-decoration: underline;
}
.empty {
  display: grid;
  justify-items: center;
  gap: 8px;
  border: 1px dashed var(--pv-line-strong, var(--pv-line));
  border-radius: var(--pv-radius-md);
  color: var(--pv-ink-dim);
  padding: 32px 24px;
  text-align: center;
}
.empty strong {
  color: var(--pv-ink);
  font-size: 1rem;
}
.empty p {
  max-width: 42ch;
  margin: 0;
  font-size: 1rem;
}
.browse-link {
  color: var(--pv-accent);
  font-size: 0.875rem;
  font-weight: 650;
  text-decoration: none;
}
.browse-link:hover {
  text-decoration: underline;
}
@media (max-width: 700px) {
  .page-heading {
    align-items: start;
    flex-direction: column;
  }
  .bids,
  .bids tbody,
  .bids tr,
  .bids td {
    display: block;
    width: 100%;
  }
  .bids thead {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
  }
  .bids tbody tr {
    padding: 14px;
    border-bottom: 1px solid var(--pv-line);
  }
  .bids tbody tr:last-child {
    border-bottom: 0;
  }
  .bids tbody td {
    display: grid;
    grid-template-columns: minmax(90px, 0.35fr) minmax(0, 1fr);
    gap: 12px;
    align-items: baseline;
    padding: 6px 0;
    border: 0;
    text-align: left;
    white-space: normal;
  }
  .bids tbody td::before {
    content: attr(data-label);
    color: var(--pv-ink-dim);
    font-size: 0.75rem;
    font-weight: 650;
    text-transform: uppercase;
  }
  .bids tbody td.actions {
    display: block;
    padding-top: 12px;
  }
  .bids tbody td.actions::before {
    display: block;
    margin-bottom: 8px;
  }
  .row-actions {
    justify-content: flex-start;
  }
}
@media (max-width: 390px) {
  .page-summary {
    font-size: 1rem;
  }
  .tablewrap {
    border-radius: var(--pv-radius-sm);
  }
  .bids tbody tr {
    padding-inline: 12px;
  }
  .row-actions :deep(button) {
    min-height: 44px;
  }
}
</style>
