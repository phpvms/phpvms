<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { computed, nextTick, ref, shallowRef, useTemplateRef } from "vue";
import AssignmentDrawer from "@/components/assignments/AssignmentDrawer.vue";
import TourCard from "@/components/tours/TourCard.vue";

/**
 * Tours page. Reads TourListItemData[] (one card per tour bundle) — types are
 * GENERATED from the PHP DTOs by `php artisan typescript:transform`
 * (App.Http.Data.* is an ambient global, no import).
 *
 * The page owns the actions the cards emit: bidding opens the shared flights
 * AssignmentDrawer on the tour's active leg (the server turns that into a
 * tour start), and cancelling drops that same leg's bid, which the tour-aware
 * destroyBid turns into a full run cancel. Both reload the `tours` prop.
 */

const props = defineProps<{
  tours: App.Http.Data.TourListItemData[];
}>();

const drawer = useTemplateRef<InstanceType<typeof AssignmentDrawer>>("drawer");
const invokingControl = shallowRef<HTMLElement | null>(null);

const filterTabs = [
  { label: "All tours", value: "all" },
  { label: "My tours", value: "mine" },
];
const tourFilter = ref<"all" | "mine">("all");

/** "My tours" = tours the pilot has a run on (any status). */
const visibleTours = computed(() =>
  tourFilter.value === "mine" ? props.tours.filter((tour) => tour.status !== null) : props.tours,
);

function openLeg(flightId: string, event: MouseEvent) {
  invokingControl.value = event.currentTarget as HTMLElement;
  drawer.value?.show(flightId);
}

async function onDrawerClosed() {
  router.reload({ only: ["tours"] });
  await nextTick();
  invokingControl.value?.focus();
}

const cancelConfirmId = ref<number | null>(null);
const cancellingId = ref<number | null>(null);
const cancelError = ref<string | null>(null);
const cancelErrorTourId = ref<number | null>(null);

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

/**
 * Dropping the active leg's bid ends the whole run server-side
 * (FlightController::destroyBid is tour-aware).
 */
async function cancelTour(tour: App.Http.Data.TourListItemData) {
  if (!tour.activeLegFlightId || cancellingId.value !== null) return;
  cancelConfirmId.value = null;
  cancellingId.value = tour.id;
  cancelError.value = null;
  cancelErrorTourId.value = null;

  try {
    const response = await fetch(`/flights/${encodeURIComponent(tour.activeLegFlightId)}/bid`, {
      method: "DELETE",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
    });
    if (!response.ok) {
      const body = (await response.json().catch(() => ({}))) as { message?: string };
      throw new Error(body.message ?? "The tour could not be cancelled. Try again.");
    }
    router.reload({ only: ["tours"] });
  } catch (error) {
    cancelError.value =
      error instanceof Error ? error.message : "The tour could not be cancelled. Try again.";
    cancelErrorTourId.value = tour.id;
  } finally {
    cancellingId.value = null;
  }
}
</script>

<template>
  <UPage class="pv-tours" aria-label="Tours">
    <UPageHeader
      class="tours-header"
      headline="Flights"
      title="Tours"
      description="Multi-leg journeys flown in order. Bid a tour once and every leg is reserved for you."
    >
      <template #links>
        <UBadge color="neutral" variant="subtle" size="lg"
          >{{ visibleTours.length }} {{ visibleTours.length === 1 ? "tour" : "tours" }}</UBadge
        >
      </template>
    </UPageHeader>

    <UPageBody>
      <UTabs
        v-model="tourFilter"
        :items="filterTabs"
        :content="false"
        size="sm"
        class="tour-filter"
      />

      <UBlogPosts v-if="visibleTours.length" class="tour-list">
        <TourCard
          v-for="tour in visibleTours"
          :key="tour.id"
          :tour="tour"
          :confirming="cancelConfirmId === tour.id"
          :cancelling="cancellingId === tour.id"
          :busy="cancellingId !== null"
          :error="cancelErrorTourId === tour.id ? cancelError : null"
          @open="openLeg"
          @confirm="cancelConfirmId = tour.id"
          @cancel-confirm="cancelConfirmId = null"
          @cancel="cancelTour(tour)"
        />
      </UBlogPosts>

      <UEmpty
        v-else-if="tourFilter === 'mine'"
        icon="i-tabler-route"
        title="No tours of your own yet"
        description="Bid a tour from the All tours list and it will show up here."
      />
      <UEmpty
        v-else
        icon="i-tabler-route"
        title="No tours yet"
        description="When your airline publishes a tour, it will appear here ready to bid."
      />
    </UPageBody>

    <AssignmentDrawer ref="drawer" @closed="onDrawerClosed" />
  </UPage>
</template>

<style scoped>
@layer components {
  .pv-tours {
    min-width: 0;
  }
  .tours-header {
    margin-bottom: 16px;
  }
  .tour-filter {
    width: fit-content;
  }
}
</style>
