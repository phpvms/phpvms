<script setup lang="ts">
/**
 * A page hero/banner. Scalar configuration is an attribute (`heading`,
 * `eyebrow`); supporting content — subtext, actions — is the default slot,
 * not a prop (`web-component-widget-floor` D3). Renders a sensible
 * placeholder with zero attributes set, so a half-written tag in a page
 * editor never looks broken (task 2.4).
 */
withDefaults(defineProps<{ heading?: string; eyebrow?: string }>(), {
  heading: "Untitled page",
});
</script>

<template>
  <header class="border-b border-gray-200 py-10">
    <p v-if="eyebrow" class="text-sm font-medium tracking-wide text-gray-500 uppercase">
      {{ eyebrow }}
    </p>
    <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ heading }}</h1>
    <div class="mt-4"><slot /></div>
  </header>
</template>
