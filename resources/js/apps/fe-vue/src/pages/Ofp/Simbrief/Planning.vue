<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, shallowRef, useTemplateRef } from "vue";
import AircraftSelection from "@/components/assignments/AircraftSelection.vue";
import SimBriefPlanningOptions from "@/components/simbrief/SimBriefPlanningOptions.vue";
import { useSimBriefAttempt } from "@/components/simbrief/useSimBriefAttempt";
import FlightIdentHeader from "@/components/flights/FlightIdentHeader.vue";
import PvLoadingState from "@/shared/components/PvLoadingState.vue";

const props = defineProps<{
  planning: App.Http.Data.SimBriefPlanningData | null;
  aircraftSelection: App.Http.Data.OFPPlanningSelectionData | null;
}>();
const attempt = useSimBriefAttempt(props.planning);
const planningOptions =
  useTemplateRef<InstanceType<typeof SimBriefPlanningOptions>>("planningOptions");
const aircraftId = shallowRef<number | null>(null);
const assigningAircraft = shallowRef(false);
const assignmentFailure = shallowRef<string | null>(null);
const flight = computed(() => props.planning?.flight ?? props.aircraftSelection?.flight ?? null);
const attemptColor = computed(() => {
  if (attempt.state.value === "complete") return "success";
  if (attempt.state.value === "error") return "error";
  return "neutral";
});
onBeforeUnmount(attempt.dispose);

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

function planningOverrides() {
  return planningOptions.value?.generate();
}

async function generate() {
  const overrides = planningOverrides();
  if (!props.planning || !overrides) return;
  await attempt.generate(overrides);
}

async function continueWithAircraft(nextAircraftId: number | null) {
  aircraftId.value = nextAircraftId;
  if (nextAircraftId === null || !props.aircraftSelection) return;

  if (props.aircraftSelection.aircraftAssignmentUrl) {
    if (assigningAircraft.value) return;

    assigningAircraft.value = true;
    assignmentFailure.value = null;
    try {
      const response = await fetch(props.aircraftSelection.aircraftAssignmentUrl, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify({ aircraftId: nextAircraftId }),
      });
      const body = (await response.json()) as {
        selection?: App.Http.Data.BidSelectionData;
        message?: string;
      };
      if (!response.ok || !body.selection?.ofpPlanningUrl) {
        throw new Error(body.message ?? "The aircraft could not be assigned to this bid.");
      }

      router.visit(body.selection.ofpPlanningUrl);
    } catch (error) {
      assignmentFailure.value =
        error instanceof Error ? error.message : "The aircraft could not be assigned to this bid.";
    } finally {
      assigningAircraft.value = false;
    }
    return;
  }

  const url = new URL(props.aircraftSelection.planningUrl, window.location.origin);
  url.searchParams.set("aircraft_id", String(nextAircraftId));
  router.visit(`${url.pathname}${url.search}${url.hash}`);
}
</script>

<template>
  <UPage class="pv-simbrief-planning">
    <UPageHeader>
      <template #description>
        <div class="planning-identity">
          <FlightIdentHeader
            v-if="flight"
            :flight="flight.summary"
            :aircraft="
              planning ? `${planning.aircraft.registration} · ${planning.aircraft.icaoType}` : null
            "
            size="lg"
          />
          <UBadge v-if="planning" :color="attemptColor" variant="soft" class="uppercase">
            {{ attempt.state.value.replaceAll("-", " ") }}
          </UBadge>
        </div>
      </template>
    </UPageHeader>

    <UPageBody class="mt-4 space-y-0 pb-0">
      <div class="planning-grid">
        <SimBriefPlanningOptions v-if="planning" ref="planningOptions" :planning="planning" />
        <UPageCard v-else-if="flight" title="Route and schedule" variant="outline">
          <dl class="facts">
            <div>
              <dt>Scheduled departure</dt>
              <dd>{{ flight.scheduledDeparture ?? "Not scheduled" }}</dd>
            </div>
            <div>
              <dt>Scheduled arrival</dt>
              <dd>{{ flight.scheduledArrival ?? "Not scheduled" }}</dd>
            </div>
          </dl>
          <p class="route">{{ flight.route || "No route published" }}</p>
        </UPageCard>

        <UPageCard
          title="Flight Plan Generation"
          description="Generate the SimBrief flight plan in a protected window. Keep this page open while the provider prepares the operational flight plan."
          variant="outline"
          class="action-panel"
          aria-label="Generate OFP"
        >
          <UAlert
            v-if="planning && attempt.state.value === 'waiting-for-popup'"
            color="info"
            variant="subtle"
            icon="i-tabler-external-link"
            description="SimBrief is open. phpVMS is checking for the completed briefing."
          />
          <PvLoadingState
            v-else-if="planning && attempt.state.value === 'polling'"
            :text="`Checking for the completed OFP (${attempt.pollAttempts.value}/60)`"
          />
          <UAlert
            v-else-if="planning && attempt.failure.value"
            color="error"
            variant="subtle"
            icon="i-tabler-alert-circle"
            :description="attempt.failure.value"
          />

          <div class="planning-actions">
            <UButton
              block
              :loading="attempt.state.value === 'requesting-code'"
              :disabled="!planning || !['ready', 'error'].includes(attempt.state.value)"
              @click="generate"
              >Generate OFP</UButton
            >
            <UButton
              v-if="planning && attempt.state.value === 'error'"
              color="neutral"
              variant="soft"
              @click="attempt.retryPoll"
              >Check for completed OFP</UButton
            >
          </div>
          <p class="muted">
            This uses a server-owned planning attempt. Your stored SimBrief username is not
            required.
          </p>

          <AircraftSelection
            v-if="aircraftSelection"
            :dispatch-url="aircraftSelection.dispatchUrl"
            :subfleets="aircraftSelection.subfleets"
            :aircraft-id="planning?.aircraft.id ?? aircraftId"
            :initial-aircraft="planning?.aircraft ?? null"
            :editable="!planning"
            :required="true"
            :selection-version="0"
            @update:aircraft-id="continueWithAircraft"
          />
          <PvLoadingState v-if="assigningAircraft" text="Saving aircraft assignment" />
          <UAlert
            v-else-if="assignmentFailure"
            color="error"
            variant="subtle"
            icon="i-tabler-alert-circle"
            :description="assignmentFailure"
          />
        </UPageCard>
      </div>
    </UPageBody>
  </UPage>
</template>

<style scoped>
.pv-simbrief-planning {
  min-width: 0;
}
.planning-identity {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}
.planning-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.45fr) minmax(18rem, 0.75fr);
  gap: 16px;
}
.facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 16px 0;
}
.facts div {
  min-width: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  padding: 11px;
}
dt {
  color: var(--pv-ink-dim);
  font-size: calc(0.75rem * var(--pv-type-scale));
  text-transform: uppercase;
}
dd {
  overflow-wrap: anywhere;
  margin: 4px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.route {
  overflow-wrap: anywhere;
  margin: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  color: var(--pv-ink);
  padding: 12px;
  font-family: var(--pv-font-mono);
  font-size: calc(0.875rem * var(--pv-type-scale));
  line-height: 1.55;
}
.action-panel {
  display: grid;
  align-content: start;
  gap: 13px;
}
.action-panel p {
  margin: 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
  line-height: 1.5;
}
.muted {
  font-size: calc(0.75rem * var(--pv-type-scale)) !important;
}
.planning-actions {
  display: grid;
  gap: 8px;
  margin-top: 4px;
}
@media (max-width: 720px) {
  .planning-identity {
    align-items: flex-start;
    flex-direction: column;
  }
  .planning-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 390px) {
  .facts {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
