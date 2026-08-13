<script setup lang="ts">
/**
 * FlightBoard — a scrollable list of FlightStrip rows with a Bid action button.
 *
 * @unused Not currently rendered by any page (Flights.vue uses FlightStrip
 * directly). Retained as a future composite widget for an inline-bidding panel.
 */
import FlightStrip, { type StripField } from "./FlightStrip.vue";

/** One row in the board. */
export interface FlightRow {
  id: string | number;
  callsign: string;
  dpt?: string | null;
  arr?: string | null;
  fields?: StripField[];
  tab?: "mag" | "green" | "amber" | "dim";
}

withDefaults(defineProps<{ flights?: FlightRow[] }>(), { flights: () => [] });

const emit = defineEmits<{ bid: [FlightRow] }>();
</script>

<template>
  <div class="board" role="list" aria-label="Flights">
    <FlightStrip
      v-for="f in flights"
      :key="f.id"
      role="listitem"
      :callsign="f.callsign"
      :dpt="f.dpt"
      :arr="f.arr"
      :fields="f.fields"
      :tab="f.tab ?? 'dim'"
    >
      <template #trailing>
        <button
          class="bid"
          type="button"
          :aria-label="`Bid on ${f.callsign}`"
          @click="emit('bid', f)"
        >
          Bid
        </button>
      </template>
    </FlightStrip>

    <div v-if="!flights.length" class="empty">No flights — enter a route to search</div>
  </div>
</template>

<style scoped>
.board {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.empty {
  font-size: 13px;
  color: var(--pv-ink-dim);
  border: 1px dashed var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 20px;
  text-align: center;
}
.bid {
  font-size: 12px;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: var(--pv-radius-md);
  border: 1px solid var(--pv-accent);
  color: var(--pv-accent);
  background: var(--pv-accent-soft);
  cursor: pointer;
  transition: background 0.15s;
}
.bid:hover {
  background: color-mix(in srgb, var(--pv-accent) 18%, transparent);
}
.bid:focus-visible {
  outline: 2px solid var(--pv-accent);
  outline-offset: 2px;
}
</style>
