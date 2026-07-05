<script setup lang="ts">
/**
 * shadcn-vue card — deliberate future-facing component. Not yet used by
 * first-party pages (which use inline panel CSS). Kept for design-system
 * consistency once shadcn-vue adoption widens.
 */
import type { HTMLAttributes } from "vue";
import { cn } from "@/shared/lib/utils";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <div :class="cn('rounded-lg border bg-card text-card-foreground shadow-sm', props.class)">
    <slot />
  </div>
</template>
