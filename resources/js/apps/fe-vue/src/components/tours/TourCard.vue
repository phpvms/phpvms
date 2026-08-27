<script setup lang="ts">
import { computed, ref } from "vue";
import TourLegTimeline from "@/components/tours/TourLegTimeline.vue";

/**
 * One tour as an image card: hero (or a placeholder), the run's status badge
 * over it, the leg chain, and the pilot's actions. Long tours collapse to a
 * window around the current leg with a toggle.
 *
 * The card owns no bid logic — it emits, and the page decides (bidding opens
 * the shared AssignmentDrawer, cancelling drops the active leg's bid).
 */

const props = defineProps<{
  tour: App.Http.Data.TourListItemData;
  confirming: boolean;
  cancelling: boolean;
  busy: boolean;
  error?: string | null;
}>();

const emit = defineEmits<{
  open: [flightId: string, event: MouseEvent];
  confirm: [];
  cancelConfirm: [];
  cancel: [];
}>();

/** Legs list collapses past this many rows. */
const MAX_LEGS_COLLAPSED = 8;
/** How many legs a collapsed list shows, windowed around the current leg. */
const LEGS_WINDOW = 6;

const expanded = ref(false);

const currentLegIndex = computed(() => {
  if (props.tour.status !== "in_progress") return 0;
  const index = props.tour.legs.findIndex((leg) => leg.flightId === props.tour.activeLegFlightId);
  return index === -1 ? 0 : index;
});

/** A window that always contains the current leg, until the pilot expands. */
const visibleLegs = computed(() => {
  const total = props.tour.legs.length;
  if (total <= MAX_LEGS_COLLAPSED || expanded.value) return props.tour.legs;
  const start = Math.min(Math.max(currentLegIndex.value - 1, 0), total - LEGS_WINDOW);
  return props.tour.legs.slice(start, start + LEGS_WINDOW);
});

/** Parse a date-only string as local time; `new Date("YYYY-MM-DD")` is UTC. */
function localDate(value: string): Date {
  const [year, month, day] = value.split("-").map(Number);
  return new Date(year, month - 1, day);
}

const dateWindow = computed(() => {
  const { startDate, endDate } = props.tour;
  if (!startDate && !endDate) return null;
  const fmt = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(localDate(value));
  if (startDate && endDate) return `${fmt(startDate)} – ${fmt(endDate)}`;
  if (startDate) return `From ${fmt(startDate)}`;
  return `Until ${fmt(endDate!)}`;
});

type BadgeColor = "primary" | "success" | "neutral" | "warning";

const statusBadge = computed<{ label: string; color: BadgeColor } | undefined>(() => {
  const labels: Record<string, { label: string; color: BadgeColor }> = {
    in_progress: {
      label: `In progress · ${props.tour.legsCompleted} of ${props.tour.legs.length} legs`,
      color: "primary",
    },
    completed: { label: "Completed", color: "success" },
    cancelled: { label: "Cancelled", color: "neutral" },
    expired: { label: "Expired", color: "warning" },
  };
  return props.tour.status ? labels[props.tour.status] : undefined;
});

const actionLabel = computed(() => {
  if (props.tour.status === "in_progress") return "Continue tour";
  if (props.tour.status === "completed") return "Fly it again";
  return "Bid this tour";
});
</script>

<template>
  <UBlogPost
    variant="outline"
    class="pv-tour-card"
    :title="props.tour.name"
    :image="props.tour.image ?? undefined"
  >
    <template v-if="dateWindow" #date>{{ dateWindow }}</template>

    <template #header="{ ui }">
      <img
        v-if="props.tour.image"
        :src="props.tour.image"
        :alt="props.tour.name"
        :class="ui.image()"
      />
      <div v-else class="image-placeholder">
        <UEmpty icon="i-tabler-photo" size="xs" />
      </div>
      <UBadge v-if="statusBadge" v-bind="statusBadge" variant="solid" class="tour-status-overlay" />
    </template>

    <p v-if="props.tour.description">{{ props.tour.description }}</p>

    <template #description>
      <TourLegTimeline v-if="props.tour.legs.length" :tour="props.tour" :legs="visibleLegs" />

      <div v-if="props.tour.legs.length > MAX_LEGS_COLLAPSED" class="legs-toggle">
        <UButton
          type="button"
          size="sm"
          color="neutral"
          variant="ghost"
          :aria-expanded="expanded"
          @click="expanded = !expanded"
          >{{ expanded ? "Show fewer legs" : `Show all ${props.tour.legs.length} legs` }}
        </UButton>
      </div>
    </template>

    <template #authors>
      <div class="tour-actions">
        <p v-if="props.error" class="cancel-error" role="alert">{{ props.error }}</p>

        <template v-if="props.confirming">
          <span class="cancel-confirm">
            <span>Cancel this tour?</span>
            <UButton
              type="button"
              size="sm"
              color="error"
              :loading="props.cancelling"
              :disabled="props.busy"
              @click="emit('cancel')"
              >Yes, cancel it</UButton
            >
            <UButton
              type="button"
              size="sm"
              color="neutral"
              variant="ghost"
              :disabled="props.busy"
              @click="emit('cancelConfirm')"
              >Keep flying</UButton
            >
          </span>
        </template>

        <template v-else>
          <UButton
            v-if="props.tour.status === 'in_progress'"
            type="button"
            size="sm"
            color="neutral"
            variant="ghost"
            :disabled="props.busy"
            @click="emit('confirm')"
            >Cancel tour</UButton
          >
          <UButton
            v-if="props.tour.activeLegFlightId"
            type="button"
            size="sm"
            @click="emit('open', props.tour.activeLegFlightId, $event)"
            >{{ actionLabel }}</UButton
          >
          <span v-else class="unavailable">This tour's legs are still being set up.</span>
        </template>
      </div>
    </template>
  </UBlogPost>
</template>

<style scoped>
@layer components {
  .image-placeholder {
    display: grid;
    width: 100%;
    height: 100%;
    place-items: center;
    background: var(--pv-panel-inset);
    color: var(--pv-ink-faint);
  }
  .tour-status-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1;
  }
  .legs-toggle {
    display: flex;
    justify-content: center;
  }
  .tour-actions {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
  }
  .cancel-confirm {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    color: var(--pv-ink-dim);
    font-size: 0.75rem;
  }
  .cancel-error {
    flex-basis: 100%;
    margin: 0;
    color: var(--pv-red);
    font-size: 0.8125rem;
    text-align: right;
  }
  .unavailable {
    color: var(--pv-ink-dim);
    font-size: 0.8125rem;
  }
}
</style>
