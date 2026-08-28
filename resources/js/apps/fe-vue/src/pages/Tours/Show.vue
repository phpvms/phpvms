<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import TourLegTimeline from "@/components/tours/TourLegTimeline.vue";
import IconArrowLeft from "~icons/tabler/arrow-left";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

/**
 * One tour: its hero image, the pilot's progress, and every leg in order.
 * Reached from the tours index and from a tour's group on My Bids. Bidding
 * happens on the index — this page is the read view of a run.
 */

const props = defineProps<{
  tour: App.Http.Data.TourListItemData;
}>();

/** Parse a date-only string as local time; `new Date("YYYY-MM-DD")` is UTC. */
function localDate(value: string): Date {
  const [year, month, day] = value.split("-").map(Number);
  return new Date(year, month - 1, day);
}

const dateWindow = (() => {
  const { startDate, endDate } = props.tour;
  if (!startDate && !endDate) return null;
  const fmt = (value: string) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(localDate(value));
  if (startDate && endDate) return `${fmt(startDate)} – ${fmt(endDate)}`;
  if (startDate) return `From ${fmt(startDate)}`;
  return `Until ${fmt(endDate!)}`;
})();

type BadgeColor = "primary" | "success" | "neutral" | "warning";

const statusBadge = (() => {
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
})();
</script>

<template>
  <UPage class="pv-tour" :aria-label="props.tour.name">
    <UPageHeader class="tour-header" headline="Flights · Tours" :title="props.tour.name">
      <template #description>
        <p v-if="props.tour.description" class="tour-description">{{ props.tour.description }}</p>
        <p v-if="dateWindow" class="tour-window">{{ dateWindow }}</p>
      </template>
      <template #links>
        <div class="header-actions">
          <UBadge v-if="statusBadge" v-bind="statusBadge" variant="subtle" size="lg" />
          <UButton to="/tours" size="sm" variant="ghost" :icon="IconArrowLeft">All tours</UButton>
        </div>
      </template>
    </UPageHeader>

    <UPageBody>
      <img
        v-if="props.tour.image"
        :src="props.tour.image"
        :alt="props.tour.name"
        class="tour-hero"
      />

      <UPageCard variant="outline" title="Legs">
        <template #body>
          <TourLegTimeline
            v-if="props.tour.legs.length"
            :tour="props.tour"
            :legs="props.tour.legs"
          />
          <p v-else class="muted">This tour's legs are still being set up.</p>
        </template>
      </UPageCard>

      <p class="back">
        <Link href="/flights/bids">Back to my bids</Link>
      </p>
    </UPageBody>
  </UPage>
</template>

<style scoped>
@layer components {
  .pv-tour {
    min-width: 0;
  }
  .tour-header {
    margin-bottom: 16px;
  }
  .header-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }
  .tour-description {
    margin: 0;
    max-width: 72ch;
  }
  .tour-window {
    margin: 4px 0 0;
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 0.75rem;
  }
  .tour-hero {
    width: 100%;
    max-height: 280px;
    object-fit: cover;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-md);
    margin-bottom: 16px;
  }
  .muted {
    margin: 0;
    color: var(--pv-ink-dim);
    font-size: 0.875rem;
  }
  .back {
    margin: 16px 0 0;
    font-size: 0.875rem;
  }
  .back a {
    color: var(--pv-accent);
    text-decoration: none;
  }
  .back a:hover {
    text-decoration: underline;
  }
}
</style>
