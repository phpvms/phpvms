<script setup lang="ts">
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
          BID
        </button>
      </template>
    </FlightStrip>

    <div v-if="!flights.length" class="empty">AWAITING QUERY · NO FLIGHTS</div>
  </div>
</template>

<style scoped>
.board {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.empty {
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
  letter-spacing: 0.16em;
  color: var(--pv-ink-dim);
  border: 1px dashed var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 20px;
  text-align: center;
}
.bid {
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.12em;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: var(--pv-radius-sm);
  text-transform: uppercase;
  border: 1px solid var(--pv-amber);
  color: var(--pv-amber);
  background: color-mix(in srgb, var(--pv-amber) 8%, transparent);
  cursor: pointer;
  transition: background 0.15s;
}
.bid:hover {
  background: color-mix(in srgb, var(--pv-amber) 18%, transparent);
}
.bid:focus-visible {
  outline: 2px solid var(--pv-cyan);
  outline-offset: 2px;
}
</style>
