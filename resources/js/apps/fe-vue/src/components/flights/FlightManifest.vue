<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed, shallowRef, watch } from "vue";
import FlightIdentHeader from "./FlightIdentHeader.vue";
import PvSlot from "@/shared/components/PvSlot.vue";
import UButton from "@nuxt/ui/components/Button.vue";

const props = defineProps<{
  flights: App.Http.Data.FlightListItemData[];
  policy: App.Http.Data.FlightDispatchPolicyData;
}>();
const emit = defineEmits<{ bid: [flightId: string, event: MouseEvent] }>();

const selectedId = shallowRef(props.flights[0]?.id ?? null);
const selected = computed(
  () => props.flights.find((flight) => flight.id === selectedId.value) ?? props.flights[0] ?? null,
);

watch(
  () => props.flights,
  (flights) => {
    if (!flights.some((flight) => flight.id === selectedId.value))
      selectedId.value = flights[0]?.id ?? null;
  },
);

function statusLabel(flight: App.Http.Data.FlightListItemData): string {
  if (flight.primaryAction === "overview") return "On bid";
  return flight.availability === "available" ? "Available" : "Unavailable";
}

function selectFlight(flightId: string): void {
  selectedId.value = flightId;
}
</script>

<template>
  <div v-if="flights.length" class="manifest-layout">
    <section class="manifest" aria-labelledby="manifest-heading">
      <header>
        <div>
          <p class="pv-eyebrow">AVAILABLE OPERATIONS</p>
          <h2 id="manifest-heading">Flight manifest</h2>
        </div>
        <span>{{ flights.length }} shown</span>
      </header>

      <div class="manifest-list" role="list">
        <article
          v-for="flight in flights"
          :key="flight.id"
          class="manifest-row"
          :class="{ selected: selected?.id === flight.id }"
          role="listitem"
          tabindex="0"
          :aria-current="selected?.id === flight.id ? 'true' : undefined"
          :aria-label="`Show ${flight.callsign} operational context`"
          @click="selectFlight(flight.id)"
          @keydown.enter.prevent="selectFlight(flight.id)"
          @keydown.space.prevent="selectFlight(flight.id)"
        >
          <div class="flight-select">
            <span class="callsign">{{ flight.callsign }}</span>
            <span class="route"
              ><strong>{{ flight.dpt ?? "—" }}</strong
              ><span>→</span><strong>{{ flight.arr ?? "—" }}</strong></span
            >
          </div>
          <dl class="row-facts">
            <div>
              <dt>Depart</dt>
              <dd>{{ flight.scheduledDeparture ?? "—" }}</dd>
            </div>
            <div>
              <dt>Arrive</dt>
              <dd>{{ flight.scheduledArrival ?? "—" }}</dd>
            </div>
            <div>
              <dt>Block</dt>
              <dd>{{ flight.blockTime ?? "—" }}</dd>
            </div>
            <div>
              <dt>Distance</dt>
              <dd>{{ flight.distanceNm == null ? "—" : `${flight.distanceNm} NM` }}</dd>
            </div>
          </dl>
          <div class="row-actions">
            <span class="availability" :class="flight.availability">{{ statusLabel(flight) }}</span>
            <Link class="detail-link" :href="`/flights/${flight.id}`" @click.stop>Details</Link>
            <UButton
              v-if="flight.primaryAction !== 'unavailable'"
              type="button"
              size="sm"
              :variant="flight.primaryAction === 'overview' ? 'soft' : 'solid'"
              @click.stop="emit('bid', flight.id, $event)"
              >{{ flight.primaryAction === "overview" ? "Overview" : "Bid" }}</UButton
            >
            <UButton v-else type="button" size="sm" disabled @click.stop>Unavailable</UButton>
            <div class="row-addon-actions" @click.stop @keydown.stop>
              <PvSlot name="flights.row.actions" :context="{ flight, policy }" />
            </div>
          </div>
          <p v-if="flight.availabilityReason" class="availability-reason">
            {{ flight.availabilityReason }}
          </p>
        </article>
      </div>
    </section>

    <aside v-if="selected" class="selected-flight" aria-labelledby="selected-flight-heading">
      <p class="pv-eyebrow">SELECTED FLIGHT</p>
      <FlightIdentHeader
        id="selected-flight-heading"
        :flight="selected"
        :href="`/flights/${selected.id}`"
        size="lg"
      />
      <dl class="selected-facts">
        <div>
          <dt>Scheduled departure</dt>
          <dd>{{ selected.scheduledDeparture ?? "Not scheduled" }}</dd>
        </div>
        <div>
          <dt>Scheduled arrival</dt>
          <dd>{{ selected.scheduledArrival ?? "Not scheduled" }}</dd>
        </div>
        <div>
          <dt>Route code</dt>
          <dd>{{ selected.routeCode ?? "—" }}</dd>
        </div>
        <div>
          <dt>Flight type</dt>
          <dd>{{ selected.type ?? "—" }}</dd>
        </div>
        <div>
          <dt>Distance</dt>
          <dd>{{ selected.distanceNm == null ? "—" : `${selected.distanceNm} NM` }}</dd>
        </div>
        <div>
          <dt>Block</dt>
          <dd>{{ selected.blockTime ?? "—" }}</dd>
        </div>
      </dl>
      <p class="ofp-note"><strong>OFP:</strong> Not generated</p>
      <div class="selected-actions">
        <UButton
          v-if="selected.primaryAction !== 'unavailable'"
          type="button"
          block
          @click="emit('bid', selected.id, $event)"
          >{{ selected.primaryAction === "overview" ? "View Bid" : "Bid on flight" }}</UButton
        >
      </div>
      <PvSlot name="flights.detail.actions" :context="{ flight: selected, policy }" />
    </aside>
  </div>

  <section v-else class="manifest-empty" aria-live="polite">
    <h2>No flights found</h2>
    <p>Change or clear the active filters to see another part of the schedule.</p>
  </section>
</template>

<style scoped>
.manifest-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.55fr) minmax(300px, 0.75fr);
  gap: 18px;
  margin-top: 18px;
}
.manifest,
.selected-flight,
.manifest-empty {
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-xl);
  background: var(--pv-panel);
  box-shadow: var(--pv-shadow-panel);
}
.manifest > header {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 20px;
  border-bottom: 1px solid var(--pv-line);
}
h2 {
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-size: calc(20px * var(--pv-type-scale));
}
.manifest > header > span {
  color: var(--pv-ink-dim);
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
}
.manifest-list {
  display: grid;
}
.manifest-row {
  position: relative;
  display: grid;
  grid-template-columns: minmax(135px, 0.65fr) minmax(220px, 1fr) minmax(120px, auto);
  align-items: center;
  gap: 16px;
  min-width: 0;
  padding: 15px 20px;
  border-bottom: 1px solid var(--pv-line);
  cursor: pointer;
}
.manifest-row:last-child {
  border-bottom: 0;
}
.manifest-row:focus-visible {
  outline: 2px solid var(--pv-focus);
  outline-offset: -3px;
  border-radius: var(--pv-radius-sm);
}
.manifest-row.selected {
  background: color-mix(in srgb, var(--pv-accent) 5%, var(--pv-panel));
  box-shadow: inset 3px 0 0 var(--pv-accent);
}
.flight-select {
  display: grid;
  gap: 4px;
  min-width: 0;
  border: 0;
  padding: 0;
  background: transparent;
  color: inherit;
  text-align: left;
}
.callsign {
  color: var(--pv-accent);
  font-family: var(--pv-font-mono);
  font-size: calc(14px * var(--pv-type-scale));
  font-weight: 750;
}
.route {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--pv-ink);
}
.route span {
  color: var(--pv-ink-faint);
}
.row-facts,
.selected-facts {
  display: grid;
  margin: 0;
}
.row-facts {
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}
.row-facts div,
.selected-facts div {
  min-width: 0;
}
dt {
  color: var(--pv-ink-faint);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
dd {
  overflow-wrap: anywhere;
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
}
.row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
  min-width: 0;
}
.row-addon-actions {
  display: contents;
}
.availability {
  border-radius: var(--pv-radius-full);
  padding: 3px 8px;
  background: var(--pv-panel-inset);
  color: var(--pv-ink-dim);
  font-size: calc(9px * var(--pv-type-scale));
  font-weight: 700;
  text-transform: uppercase;
}
.availability.available {
  color: var(--pv-green);
  background: color-mix(in srgb, var(--pv-green) 12%, transparent);
}
.detail-link {
  color: var(--pv-accent);
  font-size: calc(11px * var(--pv-type-scale));
  font-weight: 650;
  text-decoration: none;
}
.detail-link:hover {
  text-decoration: underline;
}
.availability-reason {
  grid-column: 1 / -1;
  margin: -7px 0 0;
  color: var(--pv-ink-dim);
  font-size: calc(11px * var(--pv-type-scale));
}
.selected-flight {
  align-self: start;
  position: sticky;
  top: 16px;
  padding: 22px;
}
.selected-facts {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-top: 22px;
  padding: 18px 0;
  border-block: 1px solid var(--pv-line);
}
.ofp-note {
  margin: 16px 0;
  color: var(--pv-ink-dim);
  font-size: calc(12px * var(--pv-type-scale));
}
.selected-actions {
  display: grid;
  gap: 10px;
}
.manifest-empty {
  margin-top: 18px;
  padding: 42px 24px;
  text-align: center;
}
.manifest-empty p {
  color: var(--pv-ink-dim);
}
@media (max-width: 1100px) {
  .manifest-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .selected-flight {
    position: static;
    order: -1;
  }
}
@media (max-width: 760px) {
  .manifest-row {
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
  }
  .row-facts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .row-actions {
    justify-content: flex-start;
    flex-wrap: wrap;
  }
  .availability-reason {
    grid-column: 1;
    margin-top: 0;
  }
}
@media (max-width: 390px) {
  .manifest > header,
  .manifest-row,
  .selected-flight {
    padding-inline: 14px;
  }
}
</style>
