<script setup lang="ts">
/**
 * shadcn-vue skeleton loader — deliberate future-facing component. Not yet used
 * by first-party pages. Kept for design-system consistency once shadcn-vue
 * adoption widens.
 */
import type { HTMLAttributes } from "vue";
import { cn } from "@/shared/lib/utils";

interface SkeletonProps {
  class?: HTMLAttributes["class"];
}

const props = defineProps<SkeletonProps>();
</script>

<template>
  <div :class="cn('animate-pulse rounded-md bg-muted', props.class)" />
</template>
