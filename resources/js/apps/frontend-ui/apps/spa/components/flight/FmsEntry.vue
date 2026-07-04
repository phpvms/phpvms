<script setup lang="ts">
import { ref, watch } from "vue";

/**
 * FMS-style route entry: two ICAO inputs (FROM ▸ TO) that force uppercase, plus
 * a row of filter chips. Emits `search` with the current query on submit.
 */
const props = withDefaults(defineProps<{ from?: string | null }>(), { from: null });

const emit = defineEmits<{ search: [{ from: string; to: string; filter: string }] }>();

const from = ref((props.from ?? "").toUpperCase());
const to = ref("");
const filter = ref("AIRLINE");
const filters = ["AIRLINE", "TYPE", "MAX DIST"];

watch(from, (v) => {
  from.value = v.toUpperCase();
});
watch(to, (v) => {
  to.value = v.toUpperCase();
});

function submit() {
  emit("search", { from: from.value, to: to.value, filter: filter.value });
}
</script>

<template>
  <form class="fms" role="search" @submit.prevent="submit">
    <div class="grp">
      <span class="lbl">From</span>
      <input
        v-model="from"
        class="in"
        maxlength="4"
        spellcheck="false"
        autocomplete="off"
        aria-label="Departure ICAO"
      />
    </div>
    <span class="fms-arrow" aria-hidden="true">▸</span>
    <div class="grp">
      <span class="lbl">To</span>
      <input
        v-model="to"
        class="in"
        maxlength="4"
        placeholder="____"
        spellcheck="false"
        autocomplete="off"
        aria-label="Destination ICAO"
      />
    </div>
    <div class="chips" role="group" aria-label="Filters">
      <button
        v-for="f in filters"
        :key="f"
        type="button"
        class="chip"
        :class="{ on: f === filter }"
        @click="filter = f"
      >
        {{ f }}
      </button>
    </div>
  </form>
</template>

<style scoped>
.fms {
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  box-shadow: var(--pv-shadow-panel);
}
.grp {
  display: flex;
  align-items: center;
  gap: 10px;
}
.lbl {
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
}
.in {
  font-family: var(--pv-font-mono);
  font-size: calc(13px * var(--pv-type-scale));
  font-weight: 500;
  letter-spacing: 0.08em;
  color: var(--pv-cyan);
  background: var(--pv-panel-inset);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-sm);
  padding: 6px 10px;
  width: 80px;
  text-align: center;
  text-transform: uppercase;
  caret-color: var(--pv-accent);
  transition: border-color 0.15s;
}
.in:focus {
  outline: none;
  border-color: var(--pv-accent);
  color: var(--pv-ink);
}
.in::placeholder {
  color: var(--pv-ink-dim);
}
.fms-arrow {
  font-size: calc(14px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
  font-family: var(--pv-font-mono);
}
.chips {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}
.chip {
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-sm);
  padding: 5px 10px;
  cursor: pointer;
  background: transparent;
  transition:
    border-color 0.15s,
    color 0.15s;
}
.chip:hover {
  border-color: var(--pv-cyan);
  color: var(--pv-cyan);
}
.chip.on {
  border-color: var(--pv-cyan);
  color: var(--pv-cyan);
  background: color-mix(in srgb, var(--pv-cyan) 8%, transparent);
}
.chip:focus-visible {
  outline: 2px solid var(--pv-cyan);
  outline-offset: 2px;
}
</style>
