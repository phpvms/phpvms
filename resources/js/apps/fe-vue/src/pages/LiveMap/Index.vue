<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import LiveFlightDetail from "@/components/live-map/LiveFlightDetail.vue";
import LiveFlightList from "@/components/live-map/LiveFlightList.vue";
import LiveMapCanvas from "@/components/live-map/LiveMapCanvas.vue";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

/**
 * skylight's live map (maplibre-map-platform tasks.md 7.2/7.3, map-surfaces
 * spec "skylight has its own live map page"). A thin route view: it owns
 * data fetching/polling and selection state, and composes the three feature
 * components — the map (`LiveMapCanvas`), the flight list (`LiveFlightList`),
 * and the selected-flight overlay (`LiveFlightDetail`). Polls
 * `GET api/map/live` itself at the operator-configured interval
 * (`updateIntervalSeconds`, from `livemap.update_interval` —
 * `LiveMapController::index()`); selecting a flight (marker click, via
 * `LiveMapCanvas`'s `select` event, or a list row) fetches
 * `GET api/map/pirep/{id}`.
 */
const props = defineProps<{
  updateIntervalSeconds: number;
  /** Resolved server-side (`LiveMapController::resolveInitialCenter()`) — operator setting, else the first hub airport, else `null` (package default). */
  initialCenter: { lat: number; lon: number } | null;
}>();

const flights = ref<App.Http.Data.MapLiveFlightData[]>([]);
const loading = ref(true);
const loadError = ref(false);

async function fetchFlights(): Promise<void> {
  try {
    const response = await fetch("/api/map/live", { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error(String(response.status));
    flights.value = (await response.json()) as App.Http.Data.MapLiveFlightData[];
    loadError.value = false;
  } catch {
    loadError.value = true;
  } finally {
    loading.value = false;
  }
}

let pollTimer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
  fetchFlights();
  // Guard against a misconfigured near-zero setting hammering the endpoint.
  pollTimer = setInterval(fetchFlights, Math.max(props.updateIntervalSeconds, 5) * 1000);
});
onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});

const selectedPirepId = ref<string | null>(null);
const detail = ref<App.Http.Data.MapPirepDetailData | null>(null);
const detailLoading = ref(false);
const detailError = ref(false);

async function selectFlight(pirepId: string): Promise<void> {
  selectedPirepId.value = pirepId;
  detail.value = null;
  detailError.value = false;
  detailLoading.value = true;
  try {
    const response = await fetch(`/api/map/pirep/${encodeURIComponent(pirepId)}`, {
      headers: { Accept: "application/json" },
    });
    if (!response.ok) throw new Error(String(response.status));
    detail.value = (await response.json()) as App.Http.Data.MapPirepDetailData;
  } catch {
    detailError.value = true;
  } finally {
    detailLoading.value = false;
  }
}

function clearSelection(): void {
  selectedPirepId.value = null;
  detail.value = null;
  detailError.value = false;
}
</script>

<template>
  <UPage class="pv-live-map" aria-label="Live map">
    <UPageHeader
      headline="Operations"
      title="Live flights"
      description="Flights currently in the air, plotted on the globe."
    >
      <template #links>
        <UBadge color="neutral" variant="subtle" size="lg"
          >{{ flights.length }} {{ flights.length === 1 ? "flight" : "flights" }}</UBadge
        >
      </template>
    </UPageHeader>

    <UPageBody>
      <div class="live-map-body">
        <LiveFlightList
          :flights="flights"
          :selected-pirep-id="selectedPirepId"
          :loading="loading"
          :load-error="loadError"
          @select="selectFlight"
        />

        <div class="map-pane">
          <LiveMapCanvas
            :flights="flights"
            :detail="detail"
            :selected-pirep-id="selectedPirepId"
            :initial-center="initialCenter"
            @select="selectFlight"
          />
          <LiveFlightDetail
            v-if="selectedPirepId"
            :detail="detail"
            :loading="detailLoading"
            :error="detailError"
            @close="clearSelection"
          />
        </div>
      </div>
    </UPageBody>
  </UPage>
</template>

<style scoped>
@layer components {
  .pv-live-map {
    min-width: 0;
  }
  .live-map-body {
    display: grid;
    grid-template-columns: minmax(16rem, 20rem) minmax(0, 1fr);
    grid-template-rows: auto;
    gap: 16px;
  }
  .map-pane {
    position: relative;
    min-width: 0;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-lg);
    overflow: hidden;
  }
  @media (max-width: 720px) {
    .live-map-body {
      grid-template-columns: minmax(0, 1fr);
    }
  }
}
</style>
