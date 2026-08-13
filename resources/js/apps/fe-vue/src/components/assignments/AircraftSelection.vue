<script setup lang="ts">
import { computed, shallowRef, watch } from "vue";
import PvLoadingState from "@/shared/components/PvLoadingState.vue";
import AircraftCard from "./AircraftCard.vue";
import type { EligibleAircraftResponse } from "./types";

const props = withDefaults(
  defineProps<{
    dispatchUrl: string;
    subfleets: readonly App.Http.Data.EligibleSubfleetData[];
    aircraftId: number | null;
    required: boolean;
    selectionVersion: number;
    initialAircraft?: App.Http.Data.EligibleAircraftData | null;
    editable?: boolean;
  }>(),
  { editable: true, initialAircraft: null },
);

const emit = defineEmits<{
  "update:aircraftId": [aircraftId: number | null];
  "update:loadingAircraft": [loading: boolean];
  "update:subfleetId": [subfleetId: number | null];
}>();

const aircraft = shallowRef<App.Http.Data.EligibleAircraftData[]>(
  props.initialAircraft ? [props.initialAircraft] : [],
);
const subfleetId = shallowRef<number | null>(props.initialAircraft?.subfleetId ?? null);
const loadingAircraft = shallowRef(false);
const failure = shallowRef<string | null>(null);
const editingSubfleet = shallowRef(subfleetId.value === null);
const editingAircraft = shallowRef(props.aircraftId === null);
const canEdit = computed(() => props.editable !== false);
const selectedAircraftId = computed({
  get: () => props.aircraftId,
  set: (aircraftId: number | null) => emit("update:aircraftId", aircraftId),
});
const selectedSubfleetCard = computed(
  () => props.subfleets.find((subfleet) => subfleet.id === subfleetId.value) ?? null,
);
const selectedAircraftCard = computed(
  () => aircraft.value.find((item) => item.id === selectedAircraftId.value) ?? null,
);

async function loadAircraft(next = subfleetId.value) {
  if (next === null) return;

  loadingAircraft.value = true;
  emit("update:loadingAircraft", true);
  failure.value = null;
  try {
    const response = await fetch(
      `${props.dispatchUrl}/subfleets/${encodeURIComponent(next)}/aircraft`,
      { headers: { Accept: "application/json" } },
    );
    if (!response.ok) throw new Error();
    const result = (await response.json()) as EligibleAircraftResponse;
    if (subfleetId.value === next) aircraft.value = result.aircraft;
  } catch {
    if (subfleetId.value === next) {
      failure.value =
        "Eligible aircraft could not be loaded. Choose another subfleet or try again.";
    }
  } finally {
    if (subfleetId.value === next) {
      loadingAircraft.value = false;
      emit("update:loadingAircraft", false);
    }
  }
}

function selectSubfleet(next: number | null) {
  if (next === subfleetId.value) {
    editingSubfleet.value = next === null;
    return;
  }

  subfleetId.value = next;
  emit("update:subfleetId", next);
  selectedAircraftId.value = null;
  aircraft.value = [];
  editingSubfleet.value = next === null;
  editingAircraft.value = true;
  if (next !== null) {
    void loadAircraft(next);
  } else {
    loadingAircraft.value = false;
    emit("update:loadingAircraft", false);
  }
}

function selectAircraft(next: number | null) {
  selectedAircraftId.value = next;
  editingAircraft.value = next === null;
}

function editAircraft() {
  editingAircraft.value = true;
  void loadAircraft();
}

function chooseLater() {
  selectedAircraftId.value = null;
  editingAircraft.value = true;
}

watch(
  () => props.selectionVersion,
  () => {
    subfleetId.value = null;
    aircraft.value = [];
    failure.value = null;
    loadingAircraft.value = false;
    editingSubfleet.value = true;
    editingAircraft.value = true;
    selectedAircraftId.value = null;
    emit("update:loadingAircraft", false);
    emit("update:subfleetId", null);
  },
);

defineExpose({ loadAircraft });
</script>

<template>
  <fieldset class="pv-aircraft-selector aircraft-selector">
    <legend>Aircraft Assignment</legend>
    <p class="picker-help" v-if="!selectedSubfleetCard">
      Select the subfleet and aircraft to fly with.
    </p>

    <template v-if="editingSubfleet || !selectedSubfleetCard">
      <div class="picker-heading">
        <label class="picker-label" for="bid-subfleet">Eligible subfleet</label>
        <UButton
          v-if="selectedSubfleetCard"
          size="xs"
          color="neutral"
          variant="ghost"
          @click="editingSubfleet = false"
          >Cancel</UButton
        >
      </div>
      <USelectMenu
        id="bid-subfleet"
        :model-value="subfleetId"
        :items="subfleets"
        value-key="id"
        label-key="displayName"
        :search-input="{ placeholder: 'Search subfleets' }"
        :filter-fields="['displayName', 'icaoType', 'airlineIcao', 'airlineName']"
        placeholder="Select an eligible subfleet"
        :disabled="subfleets.length === 0"
        class="select-menu"
        @update:model-value="selectSubfleet"
      >
        <template #item="{ item }">
          <div class="option-card" :class="{ unavailable: item.disabled }">
            <div class="option-main">
              <strong>{{ item.displayName }}</strong>
              <span>{{
                [item.airlineIcao, item.icaoType].filter(Boolean).join(" · ") ||
                "Configured subfleet"
              }}</span>
            </div>
            <span class="availability" :class="{ unavailable: item.disabled }">{{
              item.availabilityLabel ?? `${item.eligibleAircraftCount} available`
            }}</span>
          </div>
        </template>
        <template #empty>
          <span class="menu-empty">No configured subfleet matches this search.</span>
        </template>
      </USelectMenu>
    </template>

    <article v-else class="selected-card" aria-label="Selected subfleet">
      <header class="selected-card__header">
        <span class="selected-label">Selected subfleet</span>
        <UButton
          v-if="canEdit"
          size="xs"
          color="neutral"
          variant="ghost"
          @click="editingSubfleet = true"
          >Edit</UButton
        >
      </header>
      <strong>{{ selectedSubfleetCard.displayName }}</strong>
      <span>
        {{ selectedSubfleetCard.eligibleAircraftCount }} eligible aircraft currently available
      </span>
    </article>

    <template v-if="subfleetId !== null">
      <template v-if="editingAircraft || !selectedAircraftCard">
        <div class="picker-heading">
          <label class="picker-label" for="bid-aircraft">Eligible aircraft</label>
          <UButton
            v-if="selectedAircraftCard"
            size="xs"
            color="neutral"
            variant="ghost"
            @click="editingAircraft = false"
            >Cancel</UButton
          >
        </div>
        <USelectMenu
          id="bid-aircraft"
          :model-value="selectedAircraftId"
          :items="aircraft"
          value-key="id"
          label-key="registration"
          :search-input="{ placeholder: 'Search registrations or types' }"
          :filter-fields="['registration', 'icaoType', 'subfleetName', 'name', 'airport.icao']"
          placeholder="Select an eligible aircraft"
          :loading="loadingAircraft"
          :disabled="loadingAircraft || aircraft.length === 0"
          class="select-menu"
          @update:model-value="selectAircraft"
        >
          <template #item="{ item }">
            <div class="option-card aircraft-card">
              <div class="option-main">
                <strong>{{ item.registration }}</strong>
                <span>{{ item.icaoType }} · {{ item.subfleetName }}</span>
              </div>
              <span class="availability">{{ item.airport?.icao ?? "Airport unavailable" }}</span>
            </div>
          </template>
          <template #empty>
            <span class="menu-empty">No eligible aircraft matches this search.</span>
          </template>
        </USelectMenu>
      </template>

      <div v-if="loadingAircraft" class="aircraft-state">
        <PvLoadingState text="Loading current eligible aircraft" />
      </div>
      <div v-else-if="failure" class="aircraft-state error" role="alert">
        <strong>Aircraft unavailable</strong>
        <span>{{ failure }}</span>
        <UButton color="neutral" variant="soft" @click="loadAircraft()">Try again</UButton>
      </div>
      <div v-else-if="aircraft.length === 0" class="aircraft-state" role="status">
        <strong>No eligible aircraft</strong>
        <span v-if="required">
          No active, parked aircraft meets the current server policy for this flight.
        </span>
        <span v-else>You can still create a flight-only bid.</span>
      </div>
      <AircraftCard
        v-if="selectedAircraftCard && !editingAircraft"
        :aircraft="selectedAircraftCard"
        label="Selected aircraft"
      >
        <template #action>
          <UButton v-if="canEdit" size="xs" color="neutral" variant="ghost" @click="editAircraft"
            >Edit</UButton
          >
        </template>
      </AircraftCard>
    </template>

    <!-- <UButton
      v-if="!required"
      color="neutral"
      variant="soft"
      icon="i-tabler-clock-hour-3"
      @click="chooseLater"
    >
      Choose later
    </UButton>
    <p v-if="!required" class="picker-help">
      Choose later creates a flight-only bid. A selected aircraft is a non-exclusive preference.
    </p> -->
    <slot
      name="actions"
      :loading-aircraft="loadingAircraft"
      :selected-aircraft-id="selectedAircraftId"
    />
  </fieldset>
</template>

<style scoped>
.aircraft-selector {
  display: grid;
  min-width: 0;
  gap: 10px;
  margin: 0;
  border: 1px solid var(--pv-line-strong);
  border-radius: var(--pv-radius-lg);
  background: var(--pv-panel);
  padding: 16px;
}
.aircraft-selector legend {
  padding-inline: 4px;
  color: var(--pv-ink);
  font-size: calc(1rem * var(--pv-type-scale));
  font-weight: 750;
}
.picker-label,
.selected-label {
  color: var(--pv-ink-dim);
  font-size: calc(0.75rem * var(--pv-type-scale));
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.picker-heading,
.selected-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.picker-help {
  margin: -3px 0 4px;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.select-menu {
  min-width: 0;
  width: 100%;
}
.option-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
  padding: 4px 2px;
}
.option-card.unavailable {
  opacity: 0.68;
}
.option-main {
  display: grid;
  min-width: 0;
  gap: 2px;
}
.option-main strong {
  overflow-wrap: anywhere;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.option-main span,
.availability {
  overflow-wrap: anywhere;
  color: var(--pv-ink-dim);
  font-size: calc(0.75rem * var(--pv-type-scale));
}
.availability {
  flex: 0 0 auto;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  text-align: end;
}
.availability.unavailable {
  color: var(--pv-ink-dim);
}
.menu-empty {
  display: block;
  padding: 10px;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.selected-card,
.aircraft-state {
  display: grid;
  min-width: 0;
  gap: 4px;
  border: 1px solid var(--pv-line-strong);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  padding: 12px 14px;
}
/* .selected-card {
  box-shadow: inset 3px 0 0 var(--pv-accent);
} */
.selected-card strong,
.aircraft-state strong {
  overflow-wrap: anywhere;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.selected-card span,
.aircraft-state span {
  overflow-wrap: anywhere;
  color: var(--pv-ink-dim);
  font-size: calc(0.75rem * var(--pv-type-scale));
}
.aircraft-state {
  border-style: dashed;
}
.aircraft-state.error {
  border-color: color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
}
@media (max-width: 390px) {
  .option-card {
    align-items: flex-start;
    flex-direction: column;
    gap: 4px;
  }
  .availability {
    text-align: start;
  }
}
</style>
