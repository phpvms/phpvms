<script setup lang="ts">
/**
 * Single icon primitive for the theme. Accepts either a lucide icon NAME
 * (kebab-case like `cloud-sun` or PascalCase like `CloudSun`) OR raw inline SVG
 * child markup (legacy path strings that start with `<`). Server-provided widget
 * defs may pass either form, so both are handled here transparently.
 *
 * Stroke uses `currentColor` so the icon inherits text color, matching the
 * former hand-rolled `.i` svg usage across the theme.
 */
import { computed } from "vue";
import { icons, HelpCircle } from "lucide-vue-next";

const props = withDefaults(defineProps<{ name: string; size?: number }>(), { size: 18 });

/** True when `name` is raw inline SVG child markup rather than an icon name. */
const isRaw = computed(() => props.name.trimStart().startsWith("<"));

/** Convert `cloud-sun` / `cloudSun` / `CloudSun` → `CloudSun` for the lucide map. */
function toPascalCase(name: string): string {
  return name
    .replace(/[-_\s]+/g, " ")
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .split(" ")
    .filter(Boolean)
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join("");
}

/** Resolved lucide component, or the HelpCircle fallback for unknown names. */
const resolved = computed(() => {
  if (isRaw.value) return null;
  const key = toPascalCase(props.name) as keyof typeof icons;
  return icons[key] ?? HelpCircle;
});
</script>

<template>
  <svg
    v-if="isRaw"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    :width="size"
    :height="size"
    v-html="name"
  />
  <component :is="resolved" v-else :size="size" />
</template>
