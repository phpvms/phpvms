<script setup lang="ts">
/**
 * Single icon primitive for the theme. Accepts either a Tabler icon name
 * (kebab-case like `cloud-sun` or PascalCase like `CloudSun`) OR raw inline SVG
 * child markup (legacy path strings that start with `<`). Server-provided widget
 * defs may pass either form, so both are handled here transparently.
 *
 * A name is looked up in `dynamicIcons`, the explicit map of every icon the
 * backend may ask for; a name outside it (an addon's own) draws the fallback.
 *
 * Stroke uses `currentColor` so the icon inherits text color, matching the
 * former hand-rolled `.i` svg usage across the theme.
 */
import { computed } from "vue";
import { resolveIcon } from "@/shared/lib/dynamicIcons";

const props = withDefaults(defineProps<{ name: string; size?: number }>(), { size: 18 });

/** True when `name` is raw inline SVG child markup rather than an icon name. */
const isRaw = computed(() => props.name.trimStart().startsWith("<"));

const icon = computed(() => resolveIcon(props.name));
</script>

<template>
  <svg
    v-if="isRaw"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.75"
    :width="size"
    :height="size"
    v-html="name"
  />
  <component :is="icon" v-else :width="size" :height="size" />
</template>
