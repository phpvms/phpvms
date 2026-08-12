<script setup lang="ts">
import { computed, shallowRef } from "vue";
import { router } from "@inertiajs/vue3";
import BidAircraftPicker from "@/features/flights/BidAircraftPicker.vue";
import type { DispatchPayload, EligibleAircraftResponse } from "@/features/flights/types";

const props = defineProps<{ flight: App.Http.Data.FlightDetailData }>();
const payload = shallowRef<DispatchPayload | null>(null);
const aircraft = shallowRef<App.Http.Data.EligibleAircraftData[]>([]);
const subfleetId = shallowRef<number | null>(null);
const aircraftId = shallowRef<number | null>(null);
const loading = shallowRef(false);
const loadingAircraft = shallowRef(false);
const failure = shallowRef<string | null>(null);
const canContinue = computed(() => aircraftId.value !== null && !loadingAircraft.value);

async function load() {
  loading.value = true;
  failure.value = null;
  try {
    const response = await fetch(props.flight.dispatchUrl, {
      headers: { Accept: "application/json" },
    });
    if (!response.ok) throw new Error();
    payload.value = (await response.json()) as DispatchPayload;
  } catch {
    failure.value = "Aircraft choices could not be loaded. Check your connection and try again.";
  } finally {
    loading.value = false;
  }
}

async function selectSubfleet(next: number | null) {
  subfleetId.value = next;
  aircraftId.value = null;
  aircraft.value = [];
  if (next === null) return;
  loadingAircraft.value = true;
  failure.value = null;
  try {
    const response = await fetch(
      `${props.flight.dispatchUrl}/subfleets/${encodeURIComponent(next)}/aircraft`,
      { headers: { Accept: "application/json" } },
    );
    if (!response.ok) throw new Error();
    if (subfleetId.value === next)
      aircraft.value = ((await response.json()) as EligibleAircraftResponse).aircraft;
  } catch {
    failure.value = "Eligible aircraft could not be loaded. Choose another subfleet or try again.";
  } finally {
    if (subfleetId.value === next) loadingAircraft.value = false;
  }
}

function continueToPlanning() {
  if (aircraftId.value === null) return;
  router.visit(
    `${props.flight.simbriefPlanningUrl}&aircraft_id=${encodeURIComponent(aircraftId.value)}`,
  );
}

void load();
</script>

<template>
  <section class="simbrief-aircraft-selection" aria-labelledby="simbrief-aircraft-title">
    <p class="pv-eyebrow">SIMBRIEF · AIRCRAFT</p>
    <h3 id="simbrief-aircraft-title">Choose an aircraft for this briefing</h3>
    <p>
      Flight-only bids do not reserve an aircraft. Select one for this SimBrief plan without
      changing the bid.
    </p>
    <p v-if="loading" role="status" aria-live="polite">Loading eligible subfleets…</p>
    <p v-else-if="failure" class="selection-error" role="alert">{{ failure }}</p>
    <BidAircraftPicker
      v-else-if="payload"
      :subfleets="payload.subfleets"
      :aircraft="aircraft"
      :subfleet-id="subfleetId"
      :aircraft-id="aircraftId"
      required
      :loading-aircraft="loadingAircraft"
      @update:subfleet-id="selectSubfleet"
      @update:aircraft-id="aircraftId = $event"
    />
    <div class="selection-actions">
      <UButton color="neutral" variant="ghost" :disabled="loading" @click="load">Refresh</UButton>
      <UButton :disabled="!canContinue" @click="continueToPlanning">Continue to SimBrief</UButton>
    </div>
  </section>
</template>

<style scoped>
.simbrief-aircraft-selection {
  display: grid;
  gap: 11px;
  min-width: 0;
  border: 1px solid var(--pv-line-strong);
  border-radius: var(--pv-radius-lg);
  background: var(--pv-panel);
  padding: 16px;
}
.simbrief-aircraft-selection h3 {
  margin: 0;
  color: var(--pv-ink);
  font-size: calc(1rem * var(--pv-type-scale));
}
.simbrief-aircraft-selection > p:not(.pv-eyebrow) {
  margin: 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.selection-error {
  border: 1px solid color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
  border-radius: var(--pv-radius-md);
  color: var(--pv-red) !important;
  padding: 10px;
}
.selection-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  border-top: 1px solid var(--pv-line);
  padding-top: 12px;
}
@media (max-width: 390px) {
  .simbrief-aircraft-selection {
    padding: 14px;
  }
  .selection-actions :deep(button) {
    flex: 1;
    justify-content: center;
  }
}
</style>
