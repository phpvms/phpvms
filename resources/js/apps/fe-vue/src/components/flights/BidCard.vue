<script setup lang="ts">
import { computed } from "vue";
import FlightIdentHeader from "@/components/flights/FlightIdentHeader.vue";
import FlightStats from "@/components/flights/FlightStats.vue";
import type { FlightStat } from "@/components/flights/types";
import PvSlot from "@/shared/components/PvSlot.vue";

/**
 * One reserved dispatch as a card: the shared flight identity header (ident
 * over route), the facts a pilot checks before flying (ETE, distance,
 * aircraft, then the expiry on its own line), and the row's actions — the OFP
 * (view when one exists, generate when not) and the `bids.row.actions`
 * extension outlet the external ACARS plugin injects its Fly button into.
 *
 * Only a plain bid carries a cancel here. Dropping any leg of a tour ends the
 * whole run, so that action lives once on the tour's group header instead.
 */

const props = defineProps<{
  row: App.Http.Data.BidRowData;
  confirming: boolean;
  removing: boolean;
  busy: boolean;
}>();

const emit = defineEmits<{
  confirm: [];
  cancelConfirm: [];
  remove: [];
}>();

/** An em dash, like the other stats, when the pilot hasn't picked one yet. */
const aircraftLabel = computed(() =>
  props.row.aircraft ? `${props.row.aircraft.registration} · ${props.row.aircraft.icaoType}` : "—",
);

const stats = computed<FlightStat[]>(() => [
  { label: "ETE", value: props.row.flight?.blockTime ?? "—" },
  {
    label: "Dist",
    value: props.row.flight?.distanceNm != null ? `${props.row.flight.distanceNm}NM` : "—",
  },
  { label: "Aircraft", value: aircraftLabel.value },
]);

const expiry = computed(() => formatExpiry(props.row.expiresAt));

function formatExpiry(value: string | null): string {
  if (!value) return "No expiry";
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
</script>

<template>
  <!-- wrapper: items-stretch — the theme's `items-start` shrink-wraps every
       slot to its content, so the footer has no width to right-align in. -->
  <UPageCard
    variant="outline"
    class="pv-bid-card"
    :ui="{ container: 'gap-y-2 min-w-0 p-2.5 sm:p-3.5', wrapper: 'items-stretch min-w-0' }"
  >
    <template #header>
      <div class="head">
        <FlightIdentHeader
          v-if="props.row.flight"
          :flight="props.row.flight"
          :href="`/flights/${props.row.bid.flightId}`"
          stacked
        />
        <span v-else class="orphan">{{ props.row.bid.flightId }}</span>

        <UBadge v-if="props.row.tourName" color="primary" variant="subtle" size="sm">
          Tour<template v-if="props.row.tourLeg"> · Leg {{ props.row.tourLeg }}</template>
        </UBadge>
      </div>
    </template>

    <template #body>
      <FlightStats :stats="stats" :columns="3" />
      <p class="expiry">Expires {{ expiry }}</p>
    </template>

    <template #footer>
      <div v-if="props.confirming" class="confirm">
        <p>
          {{
            props.row.tourName
              ? `Cancelling this leg ends “${props.row.tourName}”.`
              : "Cancel this bid?"
          }}
        </p>
        <div class="actions">
          <UButton
            type="button"
            size="sm"
            color="error"
            :loading="props.removing"
            :disabled="props.busy"
            @click="emit('remove')"
            >{{ props.row.tourName ? "Cancel the tour" : "Cancel bid" }}</UButton
          >
          <UButton
            type="button"
            size="sm"
            color="neutral"
            variant="ghost"
            :disabled="props.busy"
            @click="emit('cancelConfirm')"
            >Keep</UButton
          >
        </div>
      </div>

      <div v-else class="actions">
        <!-- A tour leg has no cancel of its own: dropping any leg ends the
             whole run, so that action lives on the tour's group header. -->
        <UButton
          v-if="props.row.canRemove && !props.row.tourName"
          type="button"
          size="sm"
          color="neutral"
          variant="ghost"
          icon="i-tabler-x"
          :disabled="props.busy"
          @click="emit('confirm')"
          >Cancel</UButton
        >
        <UButton
          v-if="props.row.ofpUrl"
          :to="props.row.ofpUrl"
          size="sm"
          variant="soft"
          icon="i-tabler-file-text"
          >View OFP</UButton
        >
        <UButton
          v-else-if="props.row.canGenerateSimBrief"
          :to="`/ofp/planning?bid_id=${props.row.bid.id}`"
          size="sm"
          variant="soft"
          icon="i-tabler-file-plus"
          >Generate OFP</UButton
        >
        <PvSlot
          name="bids.row.actions"
          :context="{ bid: props.row.bid, flight: props.row.flight }"
        />
      </div>
    </template>
  </UPageCard>
</template>

<style scoped>
@layer components {
  .head {
    display: flex;
    align-items: start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 6px 10px;
    min-width: 0;
  }
  /* Pushes the tour badge right, whether it shares the row or wraps below.
     `:not(:first-child)` matters — without it a card with no badge has one
     child that is also the last, and the ident block itself gets shoved
     right. */
  .head :deep(> :last-child:not(:first-child)) {
    margin-left: auto;
  }
  /* A flex item won't shrink below its content unless every ancestor in the
     chain says it may — the header's own `stacked` prop handles the rest. */
  .head > :deep(*) {
    min-width: 0;
  }
  .orphan {
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 0.875rem;
  }
  .expiry {
    margin: 8px 0 0;
    color: var(--pv-ink-faint);
    font-family: var(--pv-font-mono);
    font-size: 0.6875rem;
  }
  .confirm {
    display: grid;
    gap: 8px;
  }
  .confirm p {
    margin: 0;
    color: var(--pv-ink-dim);
    font-size: 0.8125rem;
    text-align: right;
  }
  .actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
  }
  @media (max-width: 700px) {
    .actions :deep(button),
    .actions :deep(a) {
      min-height: 44px;
    }
  }
}
</style>
