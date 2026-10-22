<script setup lang="ts">
import { computed, shallowRef } from "vue";
import BidCard from "@/components/flights/BidCard.vue";
import IconArrowRight from "~icons/tabler/arrow-right";
import IconChecks from "~icons/tabler/checks";
import IconX from "~icons/tabler/x";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UEmpty from "@nuxt/ui/components/Empty.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageGrid from "@nuxt/ui/components/PageGrid.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

/**
 * My Bids page. Reads BidRowData[] (one card per validated bid) — types are
 * GENERATED from the PHP DTOs by `php artisan typescript:transform`
 * (App.Http.Data.* is an ambient global, no import).
 *
 * Tour legs group under their tour, with a link to that tour's overview page;
 * everything else lists below, expiring first (the controller sorts). Each
 * card keeps the `bids.row.actions` extension outlet the external ACARS
 * plugin injects into — see BidCard.
 */

const props = defineProps<{
  bids: App.Http.Data.BidRowData[];
}>();

/**
 * Two separate confirms: a plain bid card confirms by bid id, a tour
 * confirms by tour id on its group header. One shared flag would light both
 * (a tour's first leg card carries the same bid id the group cancels with).
 */
const confirmingId = shallowRef<number | null>(null);
const confirmingTourId = shallowRef<number | null>(null);
const removingId = shallowRef<number | null>(null);
const removeError = shallowRef<string | null>(null);
const removedIds = shallowRef<number[]>([]);

const visibleBids = computed(() =>
  props.bids.filter((row) => !removedIds.value.includes(row.bid.id)),
);

interface TourGroup {
  id: number;
  name: string;
  rows: App.Http.Data.BidRowData[];
}

/** Tour legs, grouped by their tour, in the order the bids arrived. */
const tourGroups = computed<TourGroup[]>(() => {
  const groups = new Map<number, TourGroup>();
  for (const row of visibleBids.value) {
    if (row.tourId == null || !row.tourName) continue;
    const group = groups.get(row.tourId) ?? { id: row.tourId, name: row.tourName, rows: [] };
    group.rows.push(row);
    groups.set(row.tourId, group);
  }
  // Legs read in flight order, not bid order.
  for (const group of groups.values()) {
    group.rows.sort((a, b) => (a.tourLeg ?? 0) - (b.tourLeg ?? 0));
  }
  return [...groups.values()];
});

const looseBids = computed(() => visibleBids.value.filter((row) => row.tourId == null));

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

/**
 * Dropping the bid; on a tour leg the server ends the whole run
 * (FlightController::destroyBid), so every leg of it leaves the page.
 */
async function removeBid(row: App.Http.Data.BidRowData) {
  if (!row.canRemove || removingId.value !== null) return;

  confirmingId.value = null;
  confirmingTourId.value = null;
  removingId.value = row.bid.id;
  removeError.value = null;

  try {
    const response = await fetch(`/flights/${encodeURIComponent(row.bid.flightId)}/bid`, {
      method: "DELETE",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
    });
    const body = (await response.json().catch(() => ({}))) as { message?: string };
    if (!response.ok) throw new Error(body.message ?? "The bid could not be removed. Try again.");

    removedIds.value = row.tourId
      ? [
          ...removedIds.value,
          ...props.bids.filter((r) => r.tourId === row.tourId).map((r) => r.bid.id),
        ]
      : [...removedIds.value, row.bid.id];
  } catch (error) {
    removeError.value =
      error instanceof Error
        ? error.message
        : "The bid could not be removed. Check your connection and try again.";
  } finally {
    removingId.value = null;
  }
}
</script>

<template>
  <UPage class="pv-my-bids" aria-label="My bids">
    <UPageHeader
      class="bids-header"
      headline="Flights"
      title="My bids"
      description="Your reserved dispatches, ready to reopen and brief."
    >
      <template #links>
        <UBadge color="neutral" variant="subtle" size="lg"
          >{{ visibleBids.length }} {{ visibleBids.length === 1 ? "bid" : "bids" }}</UBadge
        >
      </template>
    </UPageHeader>

    <UPageBody>
      <div v-if="removeError" class="remove-error" role="alert" aria-live="assertive">
        {{ removeError }}
      </div>

      <section
        v-for="group in tourGroups"
        :key="group.id"
        class="bid-group"
        :aria-labelledby="`tour-group-${group.id}`"
      >
        <header class="group-head">
          <div class="group-title">
            <UBadge color="primary" variant="subtle" size="sm">Tour</UBadge>
            <h2 :id="`tour-group-${group.id}`">{{ group.name }}</h2>
            <span class="group-count"
              >{{ group.rows.length }} {{ group.rows.length === 1 ? "leg" : "legs" }}</span
            >

            <!-- Cancelling any leg ends the run, so the action belongs to the
                 tour, not to each of its leg cards. -->
            <span v-if="confirmingTourId === group.id" class="group-confirm">
              <span>Cancel the whole tour?</span>
              <UButton
                type="button"
                size="sm"
                color="error"
                :loading="removingId !== null"
                :disabled="removingId !== null"
                @click="removeBid(group.rows[0])"
                >Yes, cancel it</UButton
              >
              <UButton
                type="button"
                size="sm"
                color="neutral"
                variant="ghost"
                :disabled="removingId !== null"
                @click="confirmingTourId = null"
                >Keep flying</UButton
              >
            </span>
            <UButton
              v-else
              type="button"
              size="sm"
              color="neutral"
              variant="ghost"
              :icon="IconX"
              :disabled="removingId !== null"
              @click="confirmingTourId = group.id"
              >Cancel tour</UButton
            >
          </div>
          <UButton
            :to="`/tours/${group.id}`"
            size="sm"
            variant="soft"
            :trailing-icon="IconArrowRight"
            >Tour overview</UButton
          >
        </header>

        <UPageGrid class="bid-grid">
          <BidCard
            v-for="row in group.rows"
            :key="row.bid.id"
            :row="row"
            :confirming="confirmingId === row.bid.id"
            :removing="removingId === row.bid.id"
            :busy="removingId !== null"
            @confirm="confirmingId = row.bid.id"
            @cancel-confirm="confirmingId = null"
            @remove="removeBid(row)"
          />
        </UPageGrid>
      </section>

      <section v-if="looseBids.length" class="bid-group" aria-labelledby="loose-bids-heading">
        <header class="group-head">
          <div class="group-title">
            <h2 id="loose-bids-heading">Bids</h2>
            <span class="group-count">Expiring first</span>
          </div>
        </header>

        <UPageGrid class="bid-grid">
          <BidCard
            v-for="row in looseBids"
            :key="row.bid.id"
            :row="row"
            :confirming="confirmingId === row.bid.id"
            :removing="removingId === row.bid.id"
            :busy="removingId !== null"
            @confirm="confirmingId = row.bid.id"
            @cancel-confirm="confirmingId = null"
            @remove="removeBid(row)"
          />
        </UPageGrid>
      </section>

      <UEmpty
        v-if="!visibleBids.length"
        :icon="IconChecks"
        title="No bids yet"
        description="Reserve a flight from the manifest to keep it here for your next dispatch."
      >
        <template #actions>
          <UButton to="/flights" variant="soft">Browse flights</UButton>
        </template>
      </UEmpty>
    </UPageBody>
  </UPage>
</template>

<style scoped>
@layer components {
  .pv-my-bids {
    min-width: 0;
  }
  .bids-header {
    margin-bottom: 16px;
  }
  .remove-error {
    margin-bottom: 12px;
    border: 1px solid color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
    border-radius: var(--pv-radius-md);
    background: color-mix(in srgb, var(--pv-red) 8%, var(--pv-panel));
    color: var(--pv-red);
    padding: 10px 12px;
    font-size: 0.875rem;
  }
  .bid-group + .bid-group {
    margin-top: 28px;
  }
  .group-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
  }
  .group-title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    min-width: 0;
  }
  .group-title h2 {
    margin: 0;
    color: var(--pv-ink);
    font-size: 1rem;
  }
  .group-count {
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 0.75rem;
  }
  .group-confirm {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    color: var(--pv-ink-dim);
    font-size: 0.75rem;
  }
  .bid-grid {
    gap: 16px;
  }
}
</style>
