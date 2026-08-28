<script setup lang="ts">
import { nextTick, shallowRef, useTemplateRef, watch } from "vue";
import { router } from "@inertiajs/vue3";
import PvLoadingState from "@/shared/components/PvLoadingState.vue";
import AircraftSelection from "./AircraftSelection.vue";
import AssignmentOverview from "./AssignmentOverview.vue";
import FlightIdentHeader from "@/components/flights/FlightIdentHeader.vue";
import { useAssignmentDrawer } from "./useAssignmentDrawer";
import IconX from "~icons/tabler/x";
import UButton from "@nuxt/ui/components/Button.vue";
import UDrawer from "@nuxt/ui/components/Drawer.vue";

const emit = defineEmits<{ closed: [] }>();
const heading = useTemplateRef<HTMLElement>("heading");
const loadingAircraft = shallowRef(false);
const {
  close,
  failure,
  load,
  open,
  payload,
  selectedAircraftId,
  selectionVersion,
  show,
  state,
  submit: submitBid,
} = useAssignmentDrawer();

async function submit() {
  const hadSelection = payload.value?.selection !== null && payload.value?.selection !== undefined;
  await submitBid();
  const selection = payload.value?.selection;
  if (!hadSelection && selection?.ofpPlanningUrl) {
    router.visit(selection.ofpPlanningUrl);
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
    :handle="false"
    :ui="{ content: 'pv-flight-bid-surface' }"
    @update:open="updateOpen"
  >
    <template #content>
      <section class="pv-flight-bid-drawer" aria-label="Flight bid">
        <header class="drawer-header">
          <div>
            <p class="pv-eyebrow">FLIGHT ASSIGNMENT</p>
            <div ref="heading" tabindex="-1">
              <FlightIdentHeader
                v-if="payload"
                :flight="payload.flight.summary"
                :aircraft="
                  payload.selection?.aircraft
                    ? `${payload.selection.aircraft.registration} · ${payload.selection.aircraft.icaoType}`
                    : null
                "
                size="lg"
              />
              <span v-else>Loading flight</span>
            </div>
          </div>
          <UButton
            type="button"
            color="neutral"
            variant="ghost"
            :icon="IconX"
            aria-label="Close flight bid"
            :disabled="state === 'submitting'"
            @click="updateOpen(false)"
          />
        </header>

        <div class="drawer-scroll">
          <div v-if="state === 'loading'" class="drawer-state">
            <PvLoadingState text="Loading policy and aircraft" />
            <p>The latest availability is checked before you can continue.</p>
          </div>

          <div v-else-if="state === 'error'" class="drawer-state error" role="alert">
            <strong>Flight data unavailable</strong>
            <p>{{ failure?.message }}</p>
            <UButton type="button" @click="load()">Try again</UButton>
          </div>

          <AssignmentOverview
            v-else-if="state === 'overview' && payload?.selection"
            :selection="payload.selection"
          />

          <form v-else-if="payload" class="bid-form" @submit.prevent="submit">
            <section class="flight-context" aria-label="Selected flight">
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

            <AircraftSelection
              :dispatch-url="payload.flight.dispatchUrl"
              :subfleets="payload.subfleets"
              :aircraft-id="selectedAircraftId"
              :required="payload.policy.aircraftRequired"
              :selection-version="selectionVersion"
              @update:aircraft-id="selectedAircraftId = $event"
              @update:loading-aircraft="loadingAircraft = $event"
            />

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
  }
}
</style>

<style>
.pv-flight-bid-surface {
  width: min(720px, 100vw) !important;
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
