<script setup lang="ts">
import { computed, reactive } from "vue";
import { useAppChrome } from "@/app/shell/useAppChrome";
import PilotIdentHeader from "../pilots/PilotIdentHeader.vue";

type ProviderFieldValue = string | number | null;
export type SimBriefEditableOverrides = Record<string, ProviderFieldValue>;

const props = defineProps<{
  planning: App.Http.Data.SimBriefPlanningData;
}>();

const emit = defineEmits<{
  generate: [overrides: SimBriefEditableOverrides];
}>();
const { user } = useAppChrome();

/**
 * See app/Http/Data/SimBriefPlanningData.php
 * it has the rest of the fields that were previously hidden in the form
 */
const fields = reactive({
  altn: String(props.planning.providerFields.altn ?? "AUTO"),
  route: String(props.planning.providerFields.route ?? ""),
  fl: String(props.planning.providerFields.fl ?? ""),
  callsign: String(props.planning.providerFields.callsign ?? ""),
  contpct: String(props.planning.providerFields.contpct ?? "0.05/5"),
  resvrule: String(props.planning.providerFields.resvrule ?? "30"),
  findSidstar: String(props.planning.providerFields.find_sidstar ?? "R"),
  stepclimbs: String(props.planning.providerFields.stepclimbs ?? "0"),
  etops: String(props.planning.providerFields.etops ?? "0"),
  planformat: String(props.planning.providerFields.planformat ?? "lido"),
  units: String(props.planning.providerFields.units ?? "LBS"),
  navlog: String(props.planning.providerFields.navlog ?? "1"),
  tlr: String(props.planning.providerFields.tlr ?? "1"),
  notams: String(props.planning.providerFields.notams ?? "1"),
  firnot: String(props.planning.providerFields.firnot ?? "0"),
  maps: String(props.planning.providerFields.maps ?? "detail"),
});

const flightLevelDisabled = computed(() => fields.stepclimbs === "1");
const callsignOptions = computed(() =>
  props.planning.callsignOptions.map((value) => ({ label: value, value })),
);
const departureTiming = computed(() => {
  const departure = new Date();
  departure.setMinutes(departure.getMinutes() + 45);
  let minutes = departure.getUTCMinutes();
  if (minutes < 55) minutes = Math.ceil(minutes / 5) * 5;
  if (minutes > 55) minutes = Math.floor(minutes / 5) * 5;

  const hour = String(departure.getUTCHours()).padStart(2, "0");
  const minute = String(minutes).padStart(2, "0");
  const months = [
    "JAN",
    "FEB",
    "MAR",
    "APR",
    "MAY",
    "JUN",
    "JUL",
    "AUG",
    "SEP",
    "OCT",
    "NOV",
    "DEC",
  ];
  const date = `${String(departure.getUTCDate()).padStart(2, "0")}${months[departure.getUTCMonth()]}${departure.getUTCFullYear()}`;
  const [hours = "0", blockMinutes = "0"] = String(
    props.planning.flight.summary.blockTime ?? "0:0",
  ).split(":");

  return {
    etd: `${hour}:${minute}`,
    date,
    deph: hour,
    depm: minute,
    steh: String(Number(hours)),
    stem: String(Number(blockMinutes)),
  };
});

const contingencyOptions = [
  { value: "0", label: "None" },
  { value: "auto", label: "AUTO" },
  { value: "easa", label: "EASA" },
  { value: "0.03/5", label: "3% or 05 MIN" },
  { value: "0.03/10", label: "3% or 10 MIN" },
  { value: "0.03/15", label: "3% or 15 MIN" },
  { value: "0.05/5", label: "5% or 05 MIN" },
  { value: "0.05/10", label: "5% or 10 MIN" },
  { value: "0.05/15", label: "5% or 15 MIN" },
  { value: "0.03", label: "3%" },
  { value: "0.05", label: "5%" },
  { value: "0.1", label: "10%" },
  { value: "0.15", label: "15%" },
  { value: "3", label: "03 MIN" },
  { value: "5", label: "05 MIN" },
  { value: "10", label: "10 MIN" },
  { value: "15", label: "15 MIN" },
];
const reserveOptions = ["auto", "0", "15", "30", "45", "60", "75", "90"].map((value) => ({
  value,
  label: value === "auto" ? "AUTO" : `${value} MIN`,
}));
const layoutOptions = [
  ["lido", "LIDO"],
  ["aal", "American Airlines"],
  ["aca", "Air Canada"],
  ["afr", "Air France (2012)"],
  ["afr2017", "Air France (2017)"],
  ["awe", "US Airways"],
  ["baw", "British Airways"],
  ["ber", "Air Berlin"],
  ["dal", "Delta Air Lines"],
  ["dlh", "Lufthansa"],
  ["ein", "Aer Lingus"],
  ["etd", "Etihad Airways"],
  ["ezy", "easyJet"],
  ["gwi", "Germanwings"],
  ["jbu", "JetBlue Airways"],
  ["jza", "Jazz Aviation"],
  ["klm", "KLM Royal Dutch Airlines"],
  ["qfa", "Qantas"],
  ["ryr", "Ryanair"],
  ["swa", "Southwest Airlines"],
  ["thy", "Turkish Airlines"],
  ["uae", "Emirates Airline"],
  ["ual", "United Airlines (2012)"],
  ["ual f:wz", "United Airlines (2018)"],
].map(([value, label]) => ({ value, label }));
const enabledOptions = [
  { value: "0", label: "Disabled" },
  { value: "1", label: "Enabled" },
];

function generate() {
  const overrides = {
    altn: fields.altn,
    route: fields.route,
    fl: flightLevelDisabled.value ? null : fields.fl,
    ...(props.planning.callsignEditable ? { callsign: fields.callsign } : {}),
    contpct: fields.contpct,
    resvrule: fields.resvrule,
    find_sidstar: fields.findSidstar,
    stepclimbs: fields.stepclimbs,
    etops: fields.etops,
    planformat: fields.planformat,
    units: fields.units,
    navlog: fields.navlog,
    tlr: fields.tlr,
    notams: fields.notams,
    firnot: fields.firnot,
    maps: fields.maps,
    date: departureTiming.value.date,
    deph: departureTiming.value.deph,
    depm: departureTiming.value.depm,
    steh: departureTiming.value.steh,
    stem: departureTiming.value.stem,
  };
  emit("generate", overrides);
  return overrides;
}

defineExpose({ generate });
</script>

<template>
  <div class="pv-planning-options planning-options">
    <UPageCard title="Flight Information" variant="outline" class="pv-form-card">
      <UPageGrid class="gap-4">
        <PilotIdentHeader v-if="user" class="sm:col-span-2 lg:col-span-3" :user="user" />

        <USeparator class="sm:col-span-2 lg:col-span-3" decorative="true" />

        <UFormField label="ATC callsign">
          <USelect
            v-if="planning.callsignEditable"
            v-model="fields.callsign"
            name="callsign"
            icon="i-tabler-broadcast"
            :items="callsignOptions"
          />
          <UInput
            v-else
            icon="i-tabler-broadcast"
            variant="subtle"
            :model-value="fields.callsign"
            disabled
          />
        </UFormField>
        <UFormField label="Departure airport">
          <UInput
            icon="i-tabler-plane-departure"
            variant="subtle"
            :model-value="planning.providerFields.orig ?? ''"
            disabled
          />
        </UFormField>
        <UFormField label="Arrival airport">
          <UInput
            icon="i-tabler-plane-arrival"
            variant="subtle"
            :model-value="planning.providerFields.dest ?? ''"
            disabled
          />
        </UFormField>
        <UFormField label="Alternate airport">
          <UInput v-model="fields.altn" name="altn" icon="i-tabler-plane-tilt" maxlength="4" />
        </UFormField>

        <UFormField label="Scheduled departure time (UTC)">
          <UInput
            icon="i-tabler-clock"
            variant="subtle"
            :model-value="planning.flight.scheduledDeparture ?? ''"
            disabled
          />
        </UFormField>
        <UFormField label="Estimated departure time (UTC)">
          <UInput
            icon="i-tabler-clock-hour-4"
            variant="subtle"
            :model-value="departureTiming.etd"
            disabled
          />
        </UFormField>
        <UFormField label="Date of flight (UTC)">
          <UInput
            icon="i-tabler-calendar"
            variant="subtle"
            :model-value="departureTiming.date"
            disabled
          />
        </UFormField>

        <UFormField class="sm:col-span-2 lg:col-span-3" label="Preferred company route">
          <UTextarea
            v-model="fields.route"
            name="route"
            icon="i-tabler-route"
            :rows="3"
            autoresize
          />
        </UFormField>
      </UPageGrid>
    </UPageCard>

    <UPageCard title="Planning Options" class="pv-form-card">
      <UPageGrid class="gap-4">
        <UFormField label="Preferred flight level">
          <UInput
            v-model="fields.fl"
            name="fl"
            icon="i-tabler-gauge"
            :variant="flightLevelDisabled ? 'ghost' : undefined"
            :disabled="flightLevelDisabled"
            maxlength="5"
          />
        </UFormField>
        <UFormField label="Cont fuel">
          <USelect
            v-model="fields.contpct"
            name="contpct"
            icon="i-tabler-percentage"
            :items="contingencyOptions"
          />
        </UFormField>
        <UFormField label="Reserve fuel">
          <USelect
            v-model="fields.resvrule"
            name="resvrule"
            icon="i-tabler-gas-station"
            :items="reserveOptions"
          />
        </UFormField>
        <UFormField label="SID/STAR type">
          <USelect
            v-model="fields.findSidstar"
            name="find_sidstar"
            icon="i-tabler-compass"
            class="w-full"
            :items="[
              { value: 'C', label: 'Conventional' },
              { value: 'R', label: 'RNAV' },
            ]"
          />
        </UFormField>
        <UFormField label="Plan stepclimbs">
          <USelect
            v-model="fields.stepclimbs"
            name="stepclimbs"
            icon="i-tabler-stairs"
            :items="enabledOptions"
          />
        </UFormField>
        <UFormField label="ETOPS planning">
          <USelect
            v-model="fields.etops"
            name="etops"
            icon="i-tabler-world"
            :items="enabledOptions"
          />
        </UFormField>
      </UPageGrid>
    </UPageCard>

    <UPageCard title="Briefing Options" class="pv-form-card">
      <UPageGrid class="gap-4">
        <UFormField label="OFP layout">
          <USelect
            v-model="fields.planformat"
            name="planformat"
            icon="i-tabler-file-description"
            :items="layoutOptions"
          />
        </UFormField>
        <UFormField label="Units">
          <USelect
            v-model="fields.units"
            name="units"
            icon="i-tabler-ruler"
            :items="[
              { value: 'KGS', label: 'KGS' },
              { value: 'LBS', label: 'LBS' },
            ]"
          />
        </UFormField>
        <UFormField label="Detailed navlog">
          <USelect
            v-model="fields.navlog"
            name="navlog"
            icon="i-tabler-list-details"
            :items="enabledOptions"
          />
        </UFormField>
        <UFormField label="Runway analysis">
          <USelect
            v-model="fields.tlr"
            name="tlr"
            icon="i-tabler-plane-inflight"
            :items="enabledOptions"
          />
        </UFormField>
        <UFormField label="Include NOTAMs">
          <USelect
            v-model="fields.notams"
            name="notams"
            icon="i-tabler-clipboard-text"
            :items="enabledOptions"
          />
        </UFormField>
        <UFormField label="FIR NOTAMs">
          <USelect
            v-model="fields.firnot"
            name="firnot"
            icon="i-tabler-alert-circle"
            :items="enabledOptions"
          />
        </UFormField>
        <UFormField label="Flight maps">
          <USelect
            v-model="fields.maps"
            name="maps"
            icon="i-tabler-map-2"
            :items="[
              { value: 'detail', label: 'Detailed' },
              { value: 'simple', label: 'Simple' },
              { value: 'none', label: 'None' },
            ]"
          />
        </UFormField>
      </UPageGrid>
    </UPageCard>
  </div>
</template>

<style scoped>
.planning-options {
  display: grid;
  gap: 16px;
  min-width: 0;
}
.planning-options__panel {
  display: grid;
  gap: 14px;
  min-width: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-lg);
  background: var(--pv-panel);
  padding: 18px;
}
</style>
