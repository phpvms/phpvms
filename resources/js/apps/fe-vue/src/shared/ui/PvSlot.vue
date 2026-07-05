<script setup lang="ts">
import { computed, type Component } from "vue";
import { usePvContext } from "@/shared/lib/usePvSlot";
import { entriesForSlot, resolveEntryProps, type SlotEntry } from "@/shared/lib/registry";

/**
 * Extension outlet. Reads the injected DATA registry, renders each entry for
 * `name` as a Vue component (resolved by name via the injected resolver map) in
 * ascending order, resolving `@`-ref props against the injected page DTO.
 * Fail-visible: an unresolved component name renders a diagnostic, never a gap.
 *
 * `context` supplies per-INSTANCE data (e.g. the current bid row). `@`-refs
 * resolve against `{ ...pageProps, ...context }` so a `@bid` ref reads the row's
 * bid even when it isn't a page-level prop; context keys override the page DTO.
 */
const props = defineProps<{ name: string; context?: Record<string, unknown> }>();

const { registry, resolver, pageProps } = usePvContext();

interface Resolved {
  entry: SlotEntry;
  component: Component | null;
  props: Record<string, unknown>;
}

const resolved = computed<Resolved[]>(() => {
  const merged = { ...pageProps, ...(props.context ?? {}) };
  return entriesForSlot(registry, props.name).map((entry) => ({
    entry,
    component: (resolver[entry.component] as Component | undefined) ?? null,
    props: resolveEntryProps(entry, merged),
  }));
});
</script>

<template>
  <template v-for="(r, i) in resolved" :key="r.entry.component + '-' + r.entry.order + '-' + i">
    <component :is="r.component" v-if="r.component" v-bind="r.props" />
    <div
      v-else
      role="alert"
      class="pv-slot-diagnostic rounded-md border px-4 py-3 text-sm"
      :data-pv-slot-failed="r.entry.component"
      :style="{
        background: 'var(--pv-slot-error-bg)',
        borderColor: 'var(--pv-slot-error-border)',
        color: 'var(--pv-slot-error-text)',
        fontFamily: 'var(--pv-font-mono)',
      }"
    >
      <strong>Extension failed to load:</strong>
      <code>{{ r.entry.component }}</code> — this slot could not be displayed.
    </div>
  </template>
</template>
