<script setup lang="ts">
import { computed } from "vue";

/**
 * A group of labelled stat tiles. `items` is a JSON-encoded list attribute —
 * the one case in the initial element set where a list attribute is
 * unavoidable, since a stat group with no items is not a useful default.
 * Malformed JSON renders a visible diagnostic instead of a blank region or a
 * thrown error (D3, task 3.2).
 */
const props = defineProps<{ items?: string }>();

interface Stat {
  label: string;
  value: string;
}

type Parsed = { ok: true; stats: Stat[] } | { ok: false; error: string };

function isStat(entry: unknown): entry is Stat {
  return (
    typeof entry === "object" &&
    entry !== null &&
    typeof (entry as Record<string, unknown>).label === "string" &&
    typeof (entry as Record<string, unknown>).value === "string"
  );
}

const parsed = computed<Parsed>(() => {
  if (!props.items) {
    return { ok: true, stats: [] };
  }

  try {
    const value: unknown = JSON.parse(props.items);
    if (!Array.isArray(value)) {
      throw new Error('"items" must be a JSON array');
    }
    value.forEach((entry, i) => {
      if (!isStat(entry)) {
        throw new Error(`item ${i} must be { "label": string, "value": string }`);
      }
    });
    return { ok: true, stats: value as Stat[] };
  } catch (e) {
    return { ok: false, error: e instanceof Error ? e.message : String(e) };
  }
});
</script>

<template>
  <div
    v-if="!parsed.ok"
    role="alert"
    class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
    data-pv-element-failed="pv-stat-group"
  >
    <strong>Malformed "items" attribute:</strong> {{ parsed.error }}
  </div>
  <p v-else-if="parsed.stats.length === 0" class="text-sm text-gray-500">No stats configured.</p>
  <dl v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3">
    <div v-for="(stat, i) in parsed.stats" :key="i" class="rounded-md border border-gray-200 p-3">
      <dt class="text-xs text-gray-500">{{ stat.label }}</dt>
      <dd class="text-lg font-semibold text-gray-900">{{ stat.value }}</dd>
    </div>
  </dl>
</template>
