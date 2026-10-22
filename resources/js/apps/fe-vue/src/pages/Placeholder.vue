<script setup lang="ts">
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

/**
 * Scaffold stub kept for routes that have no page component yet. Nothing
 * references it today — every shipped route resolves to a real page — so treat
 * it as a template rather than something to extend.
 */
</script>

<template>
  <UPage class="pv-placeholder" aria-label="Placeholder">
    <UPageHeader
      headline="skylight"
      title="Placeholder page"
      description="This route has no page component yet."
    />

    <UPageBody>
      <pre class="props-dump">{{ $page.props }}</pre>
    </UPageBody>
  </UPage>
</template>

<style scoped>
@layer components {
  .props-dump {
    overflow: auto;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-md);
    background: var(--pv-panel-inset);
    padding: 12px;
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: calc(0.75rem * var(--pv-type-scale));
  }
}
</style>
