<script setup lang="ts">
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import PvSlot from "@/shared/ui/PvSlot.vue";
import FlightRouteMap from "./FlightRouteMap.vue";
import SimBriefAircraftSelection from "@/features/simbrief/SimBriefAircraftSelection.vue";

const props = defineProps<{ selection: App.Http.Data.BidSelectionData }>();

const planningUrl = computed(() => {
  if (!props.selection.policy.simbriefAvailable || !props.selection.aircraft) return null;
  return `/simbrief/planning?flight_id=${encodeURIComponent(props.selection.flight.summary.id)}&aircraft_id=${props.selection.aircraft.id}`;
});

function formatExpiry(value: string | null): string {
  if (!value) return "Does not expire";
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function planSimBrief() {
  if (planningUrl.value) router.visit(planningUrl.value);
}
</script>

<template>
  <div class="bid-overview" aria-live="polite">
    <header>
      <div>
        <p class="pv-eyebrow">BID CONFIRMED</p>
        <h2>{{ selection.flight.summary.callsign }}</h2>
      </div>
      <span class="bid-state">{{ selection.state }}</span>
    </header>

    <p class="overview-route">
      <strong>{{ selection.flight.summary.dpt ?? "—" }}</strong
      ><span>to</span><strong>{{ selection.flight.summary.arr ?? "—" }}</strong>
    </p>
    <FlightRouteMap :flight="selection.flight" compact />

    <dl class="overview-facts">
      <div>
        <dt>Scheduled departure</dt>
        <dd>{{ selection.flight.scheduledDeparture ?? "Not scheduled" }}</dd>
      </div>
      <div>
        <dt>Scheduled arrival</dt>
        <dd>{{ selection.flight.scheduledArrival ?? "Not scheduled" }}</dd>
      </div>
      <div>
        <dt>Route</dt>
        <dd>{{ selection.flight.route ?? "Not published" }}</dd>
      </div>
      <div>
        <dt>Block</dt>
        <dd>{{ selection.flight.summary.blockTime ?? "—" }}</dd>
      </div>
      <div>
        <dt>Aircraft</dt>
        <dd>
          {{
            selection.aircraft
              ? `${selection.aircraft.registration} · ${selection.aircraft.icaoType}`
              : "Aircraft not selected"
          }}
        </dd>
      </div>
      <div>
        <dt>Reservation</dt>
        <dd>
          {{
            selection.aircraftReserved
              ? "Exclusive"
              : selection.aircraft
                ? "Preference"
                : "Flight only"
          }}
        </dd>
      </div>
      <div class="expiry">
        <dt>Expires</dt>
        <dd>{{ formatExpiry(selection.expiresAt) }}</dd>
      </div>
    </dl>

    <PvSlot name="bids.drawer.overview.actions" :context="{ bid: selection.bid, selection }" />
    <UButton v-if="planningUrl" @click="planSimBrief">Generate SimBrief</UButton>
    <SimBriefAircraftSelection
      v-else-if="selection.policy.simbriefAvailable"
      :flight="selection.flight"
    />
  </div>
</template>

<style scoped>
.bid-overview {
  display: grid;
  gap: 18px;
  min-width: 0;
}
header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
h2 {
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(24px * var(--pv-type-scale));
}
.bid-state {
  border-radius: var(--pv-radius-full);
  background: color-mix(in srgb, var(--pv-green) 13%, transparent);
  color: var(--pv-green);
  padding: 4px 9px;
  font-size: calc(10px * var(--pv-type-scale));
  font-weight: 750;
  text-transform: uppercase;
}
.overview-route {
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 0;
  color: var(--pv-ink);
  font-size: calc(18px * var(--pv-type-scale));
}
.overview-route span {
  color: var(--pv-ink-faint);
  font-size: calc(10px * var(--pv-type-scale));
  text-transform: uppercase;
}
.overview-facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin: 0;
}
.overview-facts div {
  min-width: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  padding: 12px;
}
.overview-facts .expiry {
  grid-column: 1 / -1;
}
dt {
  color: var(--pv-ink-faint);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
dd {
  overflow-wrap: anywhere;
  margin: 4px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
}
@media (max-width: 390px) {
  .overview-facts {
    grid-template-columns: minmax(0, 1fr);
  }
  .overview-facts .expiry {
    grid-column: 1;
  }
}
</style>
