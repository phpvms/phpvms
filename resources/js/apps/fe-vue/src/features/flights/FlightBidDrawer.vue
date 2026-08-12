<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from "vue";
import { router } from "@inertiajs/vue3";
import BidAircraftPicker from "./BidAircraftPicker.vue";
import BidCompactOverview from "./BidCompactOverview.vue";
import { useFlightBidDrawer } from "./useFlightBidDrawer";

const emit = defineEmits<{ closed: [] }>();
const heading = useTemplateRef<HTMLElement>("heading");
const {
  close,
  aircraft,
  failure,
  loadingAircraft,
  load,
  open,
  payload,
  selectedAircraftId,
  selectedSubfleetId,
  selectSubfleet,
  show,
  state,
  submit: submitBid,
} = useFlightBidDrawer();

async function submit() {
  const hadSelection = payload.value?.selection !== null && payload.value?.selection !== undefined;
  await submitBid();
  const selection = payload.value?.selection;
  if (!hadSelection && selection?.aircraft && selection.policy.simbriefAvailable) {
    router.visit(
      `/simbrief/planning?flight_id=${encodeURIComponent(selection.flight.summary.id)}&aircraft_id=${selection.aircraft.id}`,
    );
  }
}

watch(open, async (isOpen) => {
  if (!isOpen) return;
  await nextTick();
  heading.value?.focus();
});

function updateOpen(next: boolean) {
  if (next) return;
  close();
  if (!open.value) emit("closed");
}

defineExpose({ show });
</script>

<template>
  <UDrawer
    :open="open"
    direction="right"
    title="Flight bid"
    description="Select an eligible aircraft and confirm the flight bid."
    :dismissible="state !== 'submitting'"
    :ui="{ content: 'pv-flight-bid-surface' }"
    @update:open="updateOpen"
  >
    <template #content>
      <section class="pv-flight-bid-drawer" aria-label="Flight bid">
        <header class="drawer-header">
          <div>
            <p class="pv-eyebrow">FLIGHT BID</p>
            <h2 ref="heading" tabindex="-1">
              {{ payload?.flight.summary.callsign ?? "Loading flight" }}
            </h2>
          </div>
          <UButton
            type="button"
            color="neutral"
            variant="ghost"
            icon="i-lucide-x"
            aria-label="Close flight bid"
            :disabled="state === 'submitting'"
            @click="updateOpen(false)"
          />
        </header>

        <div class="drawer-scroll">
          <div v-if="state === 'loading'" class="drawer-state" role="status" aria-live="polite">
            <span class="state-spinner" aria-hidden="true" />
            <strong>Loading policy and aircraft</strong>
            <p>The latest availability is checked before you can continue.</p>
          </div>

          <div v-else-if="state === 'error'" class="drawer-state error" role="alert">
            <strong>Flight data unavailable</strong>
            <p>{{ failure?.message }}</p>
            <UButton type="button" @click="load()">Try again</UButton>
          </div>

          <BidCompactOverview
            v-else-if="state === 'overview' && payload?.selection"
            :selection="payload.selection"
          />

          <form v-else-if="payload" class="bid-form" @submit.prevent="submit">
            <section class="flight-context" aria-label="Selected flight">
              <p>
                <strong>{{ payload.flight.summary.dpt ?? "—" }}</strong
                ><span>→</span><strong>{{ payload.flight.summary.arr ?? "—" }}</strong>
              </p>
              <dl>
                <div>
                  <dt>Departure</dt>
                  <dd>{{ payload.flight.scheduledDeparture ?? "Not scheduled" }}</dd>
                </div>
                <div>
                  <dt>Arrival</dt>
                  <dd>{{ payload.flight.scheduledArrival ?? "Not scheduled" }}</dd>
                </div>
                <div>
                  <dt>Block</dt>
                  <dd>{{ payload.flight.summary.blockTime ?? "—" }}</dd>
                </div>
              </dl>
            </section>

            <p v-if="failure" class="bid-error" role="alert" aria-live="assertive">
              {{ failure.message }}
            </p>

            <BidAircraftPicker
              :subfleets="payload.subfleets"
              :aircraft="aircraft"
              :subfleet-id="selectedSubfleetId"
              :aircraft-id="selectedAircraftId"
              :required="payload.policy.aircraftRequired"
              :loading-aircraft="loadingAircraft"
              @update:subfleet-id="selectSubfleet"
              @update:aircraft-id="selectedAircraftId = $event"
            />

            <UButton
              v-if="
                payload.policy.aircraftRequired &&
                selectedSubfleetId !== null &&
                aircraft.length === 0 &&
                !loadingAircraft
              "
              type="button"
              color="neutral"
              variant="soft"
              icon="i-lucide-refresh-cw"
              @click="load()"
              >Refresh aircraft</UButton
            >

            <p class="policy-note">
              {{
                payload.policy.aircraftRequired
                  ? "The selected aircraft is reserved exclusively after server confirmation."
                  : "Aircraft is optional. A selection is stored as a preference and is not reserved exclusively."
              }}
            </p>

            <div class="drawer-actions">
              <UButton
                type="button"
                color="neutral"
                variant="ghost"
                :disabled="state === 'submitting'"
                @click="updateOpen(false)"
                >Cancel</UButton
              >
              <UButton
                type="button"
                :loading="state === 'submitting'"
                :disabled="
                  state === 'submitting' ||
                  (payload.policy.aircraftRequired && selectedAircraftId === null) ||
                  loadingAircraft
                "
                @click="submit"
                >{{ state === "submitting" ? "Confirming bid" : "Select" }}</UButton
              >
            </div>
            <p class="submission-status" aria-live="polite">
              {{
                state === "submitting"
                  ? "Server is revalidating flight and aircraft availability."
                  : ""
              }}
            </p>
          </form>
        </div>
      </section>
    </template>
  </UDrawer>
</template>

<style scoped>
.pv-flight-bid-drawer {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  width: 100%;
  height: 100%;
  min-width: 0;
  background: var(--pv-bg);
  color: var(--pv-ink);
}
.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--pv-line);
  background: var(--pv-panel);
  padding: 18px 20px;
}
h2 {
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(20px * var(--pv-type-scale));
  outline: none;
}
h2:focus-visible {
  outline: 2px solid var(--pv-focus);
  outline-offset: 4px;
  border-radius: var(--pv-radius-sm);
}
.drawer-scroll {
  min-width: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: 20px;
}
.drawer-state {
  display: grid;
  place-items: center;
  gap: 8px;
  min-height: 320px;
  text-align: center;
}
.drawer-state strong {
  color: var(--pv-ink);
}
.drawer-state p {
  max-width: 34ch;
  margin: 0;
  color: var(--pv-ink-dim);
}
.state-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--pv-line);
  border-top-color: var(--pv-accent);
  border-radius: 50%;
  animation: pv-bid-spin 0.8s linear infinite;
}
.bid-form {
  display: grid;
  gap: 20px;
  min-width: 0;
}
.flight-context {
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel);
  padding: 16px;
}
.flight-context > p {
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 0 0 14px;
  color: var(--pv-ink);
  font-size: calc(18px * var(--pv-type-scale));
}
.flight-context > p span {
  color: var(--pv-ink-faint);
}
.flight-context dl {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin: 0;
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
  font-size: calc(10px * var(--pv-type-scale));
}
.bid-error {
  margin: 0;
  border: 1px solid color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
  border-radius: var(--pv-radius-md);
  background: color-mix(in srgb, var(--pv-red) 8%, var(--pv-panel));
  color: var(--pv-red);
  padding: 12px 14px;
  font-size: calc(12px * var(--pv-type-scale));
}
.policy-note {
  margin: 0;
  color: var(--pv-ink-dim);
  font-size: calc(11px * var(--pv-type-scale));
}
.drawer-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  border-top: 1px solid var(--pv-line);
  padding-top: 16px;
}
.submission-status {
  min-height: 1.3em;
  margin: -14px 0 0;
  color: var(--pv-ink-dim);
  font-size: calc(10px * var(--pv-type-scale));
  text-align: right;
}
@keyframes pv-bid-spin {
  to {
    transform: rotate(360deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .state-spinner {
    animation: none;
  }
}
@media (max-width: 390px) {
  .drawer-header,
  .drawer-scroll {
    padding-inline: 14px;
  }
  .flight-context dl {
    grid-template-columns: minmax(0, 1fr);
  }
  .drawer-actions :deep(button) {
    flex: 1;
    justify-content: center;
  }
}
</style>

<style>
.pv-flight-bid-surface {
  width: min(560px, 100vw) !important;
  max-width: none !important;
  height: 100dvh;
  max-height: 100dvh !important;
  overflow: hidden;
}
@media (max-width: 640px) {
  .pv-flight-bid-surface {
    width: 100vw !important;
    height: 100dvh !important;
    border-radius: 0 !important;
  }
}
</style>
