<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { nextTick, ref, shallowRef, useTemplateRef } from "vue";
import AssignmentDrawer from "@/components/assignments/AssignmentDrawer.vue";

/**
 * Tours page. Reads TourListItemData[] (one card per tour bundle: identity +
 * schedule window + legs in order + the pilot's latest run). Types are
 * GENERATED from the PHP DTOs by `php artisan typescript:transform`
 * (App.Http.Data.* is an ambient global, no import).
 *
 * Built from Nuxt UI components end to end: UPageHeader (props, not slot
 * markup), UBlogPosts/UBlogPost cards, UTimeline for the leg chain, UButton
 * actions. The action button reuses the flights AssignmentDrawer on the
 * tour's active leg — bidding leg 1 there starts the tour on the server
 * (BidService delegates to TourService), so this page carries no bid logic
 * of its own. Closing the drawer reloads the `tours` prop so a fresh bid is
 * reflected immediately.
 */

const props = defineProps<{
  tours: App.Http.Data.TourListItemData[];
}>();

const drawer = useTemplateRef<InstanceType<typeof AssignmentDrawer>>("drawer");
const invokingControl = shallowRef<HTMLElement | null>(null);

/** Legs list collapses past this many rows. */
const MAX_LEGS_COLLAPSED = 8;
/** How many legs a collapsed list shows, windowed around the current leg. */
const LEGS_WINDOW = 6;

const expandedTours = ref<number[]>([]);

function isExpanded(tourId: number): boolean {
  return expandedTours.value.includes(tourId);
}

function toggleExpanded(tourId: number) {
  expandedTours.value = isExpanded(tourId)
    ? expandedTours.value.filter((id) => id !== tourId)
    : [...expandedTours.value, tourId];
}

function currentLegIndex(tour: App.Http.Data.TourListItemData): number {
  if (tour.status !== "in_progress") return 0;
  const index = tour.legs.findIndex((leg) => leg.flightId === tour.activeLegFlightId);
  return index === -1 ? 0 : index;
}

/** Collapsed lists show a window that always contains the current leg. */
function isLegVisible(tour: App.Http.Data.TourListItemData, index: number): boolean {
  const total = tour.legs.length;
  if (total <= MAX_LEGS_COLLAPSED || isExpanded(tour.id)) return true;
  const start = Math.min(Math.max(currentLegIndex(tour) - 1, 0), total - LEGS_WINDOW);
  return index >= start && index < start + LEGS_WINDOW;
}

interface LegTimelineItem {
  value: string;
  ident: string;
  dpt: string;
  arr: string;
  state?: "next" | "flown";
  icon?: string;
  avatar?: { text: string };
}

/** The visible legs as UTimeline items; flown legs wear a check indicator. */
function timelineItems(tour: App.Http.Data.TourListItemData): LegTimelineItem[] {
  return tour.legs
    .filter((_, index) => isLegVisible(tour, index))
    .map((leg) => ({
      value: leg.flightId,
      ident: leg.ident,
      dpt: leg.dpt,
      arr: leg.arr,
      state: leg.flown
        ? "flown"
        : tour.status === "in_progress" && leg.flightId === tour.activeLegFlightId
          ? "next"
          : undefined,
      icon: leg.flown ? "i-tabler-check" : undefined,
      avatar: leg.flown ? undefined : { text: String(leg.routeLeg ?? "") },
    }));
}

/**
 * UTimeline's active value: the current leg of a run in progress (legs before
 * it read as completed), one past the end for a finished run, none otherwise.
 */
function timelineValue(tour: App.Http.Data.TourListItemData): string | number | undefined {
  if (tour.status === "completed") return tour.legs.length;
  if (tour.status === "in_progress") return tour.activeLegFlightId ?? undefined;
  return undefined;
}

function openLeg(flightId: string, event: MouseEvent) {
  invokingControl.value = event.currentTarget as HTMLElement;
  drawer.value?.show(flightId);
}

async function onDrawerClosed() {
  router.reload({ only: ["tours"] });
  await nextTick();
  invokingControl.value?.focus();
}

/** Parse a date-only string as local time; `new Date("YYYY-MM-DD")` is UTC. */
function localDate(value: string): Date {
  const [year, month, day] = value.split("-").map(Number);
  return new Date(year, month - 1, day);
}

function formatWindow(tour: App.Http.Data.TourListItemData): string | null {
  if (!tour.startDate && !tour.endDate) return null;
  const fmt = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(localDate(value));
  if (tour.startDate && tour.endDate) return `${fmt(tour.startDate)} – ${fmt(tour.endDate)}`;
  if (tour.startDate) return `From ${fmt(tour.startDate)}`;
  return `Until ${fmt(tour.endDate!)}`;
}

function statusLabel(tour: App.Http.Data.TourListItemData): string | null {
  switch (tour.status) {
    case "in_progress":
      return `In progress · ${tour.legsCompleted} of ${tour.legs.length} legs`;
    case "completed":
      return "Completed";
    case "cancelled":
      return "Cancelled";
    case "expired":
      return "Expired";
    default:
      return null;
  }
}

function actionLabel(tour: App.Http.Data.TourListItemData): string {
  if (tour.status === "in_progress") return "Continue tour";
  if (tour.status === "completed") return "Fly it again";
  return "Bid this tour";
}

type BadgeColor = "primary" | "success" | "neutral" | "warning";

function statusBadge(
  tour: App.Http.Data.TourListItemData,
): { label: string; color: BadgeColor } | undefined {
  const label = statusLabel(tour);
  if (!label) return undefined;
  const colors: Record<string, BadgeColor> = {
    in_progress: "primary",
    completed: "success",
    cancelled: "neutral",
    expired: "warning",
  };
  return { label, color: colors[tour.status ?? ""] ?? "neutral" };
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
          >{{ props.tours.length }} {{ props.tours.length === 1 ? "tour" : "tours" }}</UBadge
        >
      </template>
    </UPageHeader>

    <UBlogPosts v-if="props.tours.length" class="tour-list">
      <UBlogPost
        v-for="tour in props.tours"
        :key="tour.id"
        variant="outline"
        class="tour-card"
        :title="tour.name"
        :description="tour.description ?? undefined"
        :image="tour.image ?? undefined"
        :badge="statusBadge(tour)"
      >
        <template v-if="formatWindow(tour)" #date>{{ formatWindow(tour) }}</template>

        <template v-if="!tour.image" #header>
          <div class="image-placeholder">
            <UEmpty icon="i-tabler-photo" size="xs" />
          </div>
        </template>

        <template #footer>
          <UTimeline
            v-if="tour.legs.length"
            :items="timelineItems(tour)"
            :default-value="timelineValue(tour)"
            color="primary"
            size="xs"
            class="tour-legs"
            :ui="{ wrapper: 'mt-0 pb-4', title: 'leading-6' }"
          >
            <template #title="{ item }">
              <span class="leg-line">
                <span class="leg-ident">{{ (item as LegTimelineItem).ident }}</span>
                <span class="leg-route"
                  >{{ (item as LegTimelineItem).dpt }} <span aria-hidden="true">→</span>
                  <span class="sr-only">to</span> {{ (item as LegTimelineItem).arr }}</span
                >
                <span
                  v-if="(item as LegTimelineItem).state"
                  class="leg-state"
                  :data-state="(item as LegTimelineItem).state"
                  >{{ (item as LegTimelineItem).state === "next" ? "Up next" : "Flown" }}</span
                >
              </span>
            </template>
          </UTimeline>

          <div v-if="tour.legs.length > MAX_LEGS_COLLAPSED" class="legs-toggle">
            <UButton
              type="button"
              size="sm"
              color="neutral"
              variant="ghost"
              :aria-expanded="isExpanded(tour.id)"
              @click="toggleExpanded(tour.id)"
              >{{ isExpanded(tour.id) ? "Show fewer legs" : `Show all ${tour.legs.length} legs` }}
            </UButton>
          </div>

          <div class="tour-foot">
            <UButton
              v-if="tour.activeLegFlightId"
              type="button"
              size="sm"
              @click="openLeg(tour.activeLegFlightId, $event)"
              >{{ actionLabel(tour) }}</UButton
            >
            <span v-else class="unavailable">This tour's legs are still being set up.</span>
          </div>
        </template>
      </UBlogPost>
    </UBlogPosts>

    <div v-else class="empty">
      <strong>No tours yet</strong>
      <p>When your airline publishes a tour, it will appear here ready to bid.</p>
    </div>

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
  .image-placeholder {
    display: grid;
    width: 100%;
    height: 100%;
    place-items: center;
    background: var(--pv-panel-inset);
    color: var(--pv-ink-faint);
  }
  .leg-line {
    display: inline-flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
  }
  .leg-ident {
    font-family: var(--pv-font-mono);
    font-weight: 650;
  }
  .leg-route {
    font-family: var(--pv-font-mono);
  }
  .leg-state {
    font-size: 0.6875rem;
    font-weight: 750;
    text-transform: uppercase;
  }
  .leg-state[data-state="next"] {
    color: var(--pv-accent);
  }
  .leg-state[data-state="flown"] {
    color: color-mix(in srgb, var(--pv-green) 70%, var(--pv-ink));
  }
  .sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
  }
  .legs-toggle {
    display: flex;
    justify-content: center;
  }
  .tour-foot {
    display: flex;
    justify-content: flex-end;
  }
  .unavailable {
    width: 100%;
    border: 1px dashed var(--pv-line-strong, var(--pv-line));
    border-radius: var(--pv-radius-sm);
    color: var(--pv-ink-dim);
    font-size: 0.8125rem;
    padding: 16px;
    text-align: center;
  }
  .empty {
    display: grid;
    justify-items: center;
    gap: 8px;
    border: 1px dashed var(--pv-line-strong, var(--pv-line));
    border-radius: var(--pv-radius-md);
    color: var(--pv-ink-dim);
    padding: 32px 24px;
    text-align: center;
  }
  .empty strong {
    color: var(--pv-ink);
    font-size: 1rem;
  }
  .empty p {
    max-width: 42ch;
    margin: 0;
    font-size: 1rem;
  }
  @media (max-width: 700px) {
    .tour-foot :deep(button),
    .legs-toggle :deep(button) {
      min-height: 44px;
    }
  }
}
</style>
