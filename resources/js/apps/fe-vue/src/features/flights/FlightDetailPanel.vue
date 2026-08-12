<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import PvSlot from "@/shared/ui/PvSlot.vue";
import AirportWeather from "./AirportWeather.vue";
import FlightRouteMap from "./FlightRouteMap.vue";

defineProps<{
  flight: App.Http.Data.FlightDetailData;
  policy: App.Http.Data.FlightDispatchPolicyData;
}>();
const emit = defineEmits<{ bid: [flightId: string, event: MouseEvent] }>();
</script>

<template>
  <article class="flight-detail">
    <header class="detail-hero">
      <div>
        <p class="pv-eyebrow">FLIGHT DISPATCH</p>
        <h1>{{ flight.summary.callsign }}</h1>
        <p class="route">
          <strong>{{ flight.summary.dpt ?? "—" }}</strong
          ><span>to</span><strong>{{ flight.summary.arr ?? "—" }}</strong>
        </p>
      </div>
      <div class="detail-actions">
        <UButton
          v-if="flight.summary.primaryAction !== 'unavailable'"
          type="button"
          @click="emit('bid', flight.summary.id, $event)"
          >{{
            flight.summary.primaryAction === "overview" ? "Open bid overview" : "Bid on flight"
          }}</UButton
        >
        <UButton v-else type="button" disabled>Unavailable</UButton>
        <Link
          v-if="
            policy.simbriefAvailable &&
            (!policy.simbriefRequiresBid || flight.summary.primaryAction === 'overview')
          "
          class="simbrief-link"
          :href="flight.simbriefPlanningUrl"
          >Generate SimBrief</Link
        >
        <PvSlot name="flights.detail.actions" :context="{ flight, policy }" />
      </div>
    </header>

    <dl class="detail-facts">
      <div>
        <dt>Scheduled departure</dt>
        <dd>{{ flight.scheduledDeparture ?? "Not scheduled" }}</dd>
      </div>
      <div>
        <dt>Scheduled arrival</dt>
        <dd>{{ flight.scheduledArrival ?? "Not scheduled" }}</dd>
      </div>
      <div>
        <dt>Distance</dt>
        <dd>{{ flight.summary.distanceNm == null ? "—" : `${flight.summary.distanceNm} NM` }}</dd>
      </div>
      <div>
        <dt>Block time</dt>
        <dd>{{ flight.summary.blockTime ?? "—" }}</dd>
      </div>
      <div>
        <dt>Route code</dt>
        <dd>{{ flight.summary.routeCode ?? "—" }}</dd>
      </div>
      <div>
        <dt>Cruise</dt>
        <dd>{{ flight.cruiseLevel == null ? "—" : `FL${flight.cruiseLevel}` }}</dd>
      </div>
    </dl>

    <section class="route-block" aria-labelledby="route-map-heading">
      <div class="section-heading">
        <div>
          <p class="pv-eyebrow">ROUTE</p>
          <h2 id="route-map-heading">Operational route</h2>
        </div>
        <p>{{ flight.route ?? "Route not published" }}</p>
      </div>
      <FlightRouteMap :flight="flight" />
    </section>

    <section class="weather-grid" aria-label="Airport weather">
      <AirportWeather label="Departure weather" :station="flight.departureWeather" />
      <AirportWeather label="Arrival weather" :station="flight.arrivalWeather" />
      <AirportWeather
        label="Alternate weather"
        :station="flight.alternateWeather"
        empty-label="No alternate airport"
      />
    </section>

    <section class="ofp-state" aria-label="Operational flight plan state">
      <p class="pv-eyebrow">OFP</p>
      <strong>Not generated</strong>
      <span>SimBrief planning is a separate step and does not start when a bid is placed.</span>
    </section>
  </article>
</template>

<style scoped>
.flight-detail {
  display: grid;
  gap: 18px;
  min-width: 0;
}
.detail-hero,
.detail-facts,
.route-block,
.ofp-state {
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-xl);
  background: var(--pv-panel);
  box-shadow: var(--pv-shadow-panel);
}
.detail-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 24px;
}
h1 {
  margin: 3px 0 8px;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(30px * var(--pv-type-scale));
}
.route {
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 0;
  color: var(--pv-ink);
  font-size: calc(18px * var(--pv-type-scale));
}
.route span {
  color: var(--pv-ink-faint);
  font-size: calc(11px * var(--pv-type-scale));
  text-transform: uppercase;
}
.detail-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.simbrief-link {
  color: var(--pv-accent);
  font-size: calc(12px * var(--pv-type-scale));
  font-weight: 650;
  text-decoration: none;
}
.simbrief-link:hover {
  text-decoration: underline;
}
.detail-facts {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  margin: 0;
  padding: 18px 22px;
}
.detail-facts div {
  min-width: 0;
  padding-inline: 14px;
  border-right: 1px solid var(--pv-line);
}
.detail-facts div:first-child {
  padding-left: 0;
}
.detail-facts div:last-child {
  padding-right: 0;
  border-right: 0;
}
dt {
  color: var(--pv-ink-faint);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
dd {
  overflow-wrap: anywhere;
  margin: 5px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
}
.route-block {
  padding: 20px;
}
.section-heading {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}
h2 {
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-size: calc(19px * var(--pv-type-scale));
}
.section-heading > p {
  max-width: 50%;
  margin: 0;
  color: var(--pv-ink-dim);
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
  text-align: right;
}
.weather-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}
.ofp-state {
  display: grid;
  grid-template-columns: auto auto minmax(0, 1fr);
  align-items: center;
  gap: 14px;
  padding: 18px 22px;
}
.ofp-state strong {
  color: var(--pv-ink);
}
.ofp-state span {
  color: var(--pv-ink-dim);
  font-size: calc(12px * var(--pv-type-scale));
}
@media (max-width: 900px) {
  .detail-facts {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px 0;
  }
  .detail-facts div:nth-child(3) {
    border-right: 0;
  }
  .weather-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 600px) {
  .detail-hero {
    align-items: stretch;
    flex-direction: column;
    padding: 18px;
  }
  .detail-actions {
    align-items: stretch;
    flex-direction: column;
  }
  .detail-facts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .detail-facts div:nth-child(3) {
    border-right: 1px solid var(--pv-line);
  }
  .detail-facts div:nth-child(even) {
    border-right: 0;
  }
  .section-heading {
    align-items: start;
    flex-direction: column;
  }
  .section-heading > p {
    max-width: none;
    text-align: left;
  }
  .ofp-state {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
