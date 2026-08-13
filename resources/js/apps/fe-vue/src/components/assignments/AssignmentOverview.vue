<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { computed } from "vue";
import AircraftCard from "./AircraftCard.vue";
import FlightIdentHeader from "@/components/flights/FlightIdentHeader.vue";
import PvSlot from "@/shared/components/PvSlot.vue";

const props = defineProps<{ selection: App.Http.Data.BidSelectionData }>();

const ofpAction = computed(() => {
  if (props.selection.ofpGenerated && props.selection.ofpUrl) {
    return { label: "View OFP", url: props.selection.ofpUrl };
  }

  if (props.selection.policy.simbriefEnabled && props.selection.ofpPlanningUrl) {
    return { label: "Generate OFP", url: props.selection.ofpPlanningUrl };
  }

  return null;
});

function formatExpiry(value: string | null): string {
  if (!value) return "Does not expire";
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function visitOfp() {
  if (ofpAction.value) router.visit(ofpAction.value.url);
}
</script>

<template>
  <div class="bid-overview" aria-live="polite">
    <!-- <header>
      <div>
        <p class="pv-eyebrow">BID CONFIRMED</p>
        <FlightIdentHeader
          :flight="selection.flight.summary"
          :aircraft="selection.aircraft ? `${selection.aircraft.registration} · ${selection.aircraft.icaoType}` : 'Aircraft not selected'"
          size="lg"
        />
      </div>
      <span class="bid-state">{{ selection.state }}</span>
    </header> -->

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

    <AircraftCard :aircraft="selection.aircraft" label="Aircraft" />

    <div class="overview-actions">
      <PvSlot name="bids.drawer.overview.actions" :context="{ bid: selection.bid, selection }" />
      <UButton v-if="ofpAction" @click="visitOfp">{{ ofpAction.label }}</UButton>
    </div>
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
.bid-state {
  border-radius: var(--pv-radius-full);
  background: color-mix(in srgb, var(--pv-green) 13%, transparent);
  color: var(--pv-green);
  padding: 4px 9px;
  font-size: calc(10px * var(--pv-type-scale));
  font-weight: 750;
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
.overview-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
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
