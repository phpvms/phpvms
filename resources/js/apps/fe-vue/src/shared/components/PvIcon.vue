<script setup lang="ts">
/**
 * Single icon primitive for the theme. Accepts either a Tabler icon name
 * (kebab-case like `cloud-sun` or PascalCase like `CloudSun`) OR raw inline SVG
 * child markup (legacy path strings that start with `<`). Server-provided widget
 * defs may pass either form, so both are handled here transparently.
 *
 * Stroke uses `currentColor` so the icon inherits text color, matching the
 * former hand-rolled `.i` svg usage across the theme.
 */
import { computed } from "vue";

const props = withDefaults(defineProps<{ name: string; size?: number }>(), { size: 18 });

/** True when `name` is raw inline SVG child markup rather than an icon name. */
const isRaw = computed(() => props.name.trimStart().startsWith("<"));

/** Convert `cloudSun` / `CloudSun` to Iconify's `cloud-sun` name. */
function toKebabCase(name: string): string {
  return name
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
    .replace(/[_\s]+/g, "-")
    .toLowerCase();
}

const resolvedName = computed(() => {
  const name = props.name.trim();
  if (name.startsWith("i-tabler-")) return name;
  return `i-tabler-${toKebabCase(name.replace(/^i-[^-]+-/, ""))}`;
});
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
  <UIcon v-else :name="resolvedName" :size="size" />
</template>
