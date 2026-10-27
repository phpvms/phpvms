<script setup lang="ts">
import { computed } from "vue";

/**
 * An inline callout/alert box. `variant` is a scalar attribute; body content
 * is the default slot (D3). An unrecognized variant falls back to `info`
 * instead of throwing, so mistyped markup still renders something sensible.
 */
const props = withDefaults(defineProps<{ variant?: string }>(), { variant: "info" });

const VARIANT_CLASSES: Record<string, string> = {
  info: "border-blue-300 bg-blue-50 text-blue-900",
  success: "border-green-300 bg-green-50 text-green-900",
  warning: "border-amber-300 bg-amber-50 text-amber-900",
  error: "border-red-300 bg-red-50 text-red-900",
};

const classes = computed(() => VARIANT_CLASSES[props.variant] ?? VARIANT_CLASSES.info);
</script>

<template>
  <div role="note" class="rounded-md border px-4 py-3 text-sm" :class="classes">
    <slot>Callout</slot>
  </div>
</template>
