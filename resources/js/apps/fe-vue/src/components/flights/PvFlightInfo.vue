<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    callsign: string;
    departure: string;
    arrival: string;
    href?: string;
    size?: "sm" | "md" | "lg";
  }>(),
  { size: "md" },
);

const root = computed(() => (props.href ? Link : "span"));
const rootProps = computed(() => (props.href ? { href: props.href } : {}));
</script>

<template>
  <component
    :is="root"
    v-bind="rootProps"
    class="pv-flight-info"
    :class="`pv-flight-info--${size}`"
  >
    <span class="pv-flight-info__callsign">{{ callsign }}</span>
    <span class="pv-flight-info__route">
      <span>{{ departure }}</span>
      <span class="pv-flight-info__arrow" aria-hidden="true">→</span>
      <span class="pv-flight-info__spoken">to</span>
      <span>{{ arrival }}</span>
    </span>
  </component>
</template>

<style scoped>
.pv-flight-info {
  display: inline-flex;
  align-items: baseline;
  gap: 0.55em;
  max-width: 100%;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-weight: 750;
  line-height: 1.25;
  text-decoration: none;
  white-space: nowrap;
}
.pv-flight-info--sm {
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.pv-flight-info--md {
  font-size: calc(1rem * var(--pv-type-scale));
}
.pv-flight-info--lg {
  font-size: calc(1.25rem * var(--pv-type-scale));
}
.pv-flight-info__callsign {
  color: var(--pv-accent);
}
.pv-flight-info__route {
  display: inline-flex;
  align-items: baseline;
  gap: 0.4em;
}
.pv-flight-info__arrow {
  color: var(--pv-ink-dim);
}
.pv-flight-info__spoken {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  clip-path: inset(50%);
}
a.pv-flight-info:hover {
  color: var(--pv-accent);
}
a.pv-flight-info:focus-visible {
  border-radius: var(--pv-radius-sm);
  outline: 2px solid var(--pv-focus);
  outline-offset: 4px;
}
</style>
