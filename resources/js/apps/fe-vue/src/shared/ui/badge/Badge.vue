<script setup lang="ts">
/**
 * shadcn-vue badge — deliberate future-facing component. Not yet used by
 * first-party pages (which use inline badge CSS). Kept for design-system
 * consistency once shadcn-vue adoption widens.
 */
import type { HTMLAttributes } from "vue";
import type { BadgeVariants } from ".";
import { cn } from "@/shared/lib/utils";
import { badgeVariants } from ".";

const props = defineProps<{
  variant?: BadgeVariants["variant"];
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <div :class="cn(badgeVariants({ variant }), props.class)">
    <slot />
  </div>
</template>
