<script setup lang="ts">
import { useSlots, computed } from "vue";

/**
 * The DEFAULT PvApp layout: a top header, a centered `.pv-container` main
 * column, and an optional footer — no side rails. Container width is backed by
 * `--pv-container-width` (editable in app.css and runtime-overridable).
 */
const slots = useSlots();
const hasHeader = computed(() => !!slots.header);
const hasFooter = computed(() => !!slots.footer);
</script>

<template>
  <div class="pv-centered">
    <header v-if="hasHeader" class="pv-centered-header" role="banner">
      <div class="pv-container flex items-center justify-between h-full">
        <slot name="header" />
      </div>
    </header>

    <main class="pv-centered-main" role="main">
      <div class="pv-container">
        <slot name="main" />
        <slot />
      </div>
    </main>

    <footer v-if="hasFooter" class="pv-centered-footer">
      <div class="pv-container">
        <slot name="footer" />
      </div>
    </footer>
  </div>
</template>

<style scoped>
.pv-centered {
  min-height: 100vh;
  background: var(--pv-bg);
  display: flex;
  flex-direction: column;
}
.pv-centered-header {
  height: var(--pv-header-height, 48px);
  background: var(--pv-panel);
  border-bottom: 1px solid var(--pv-line);
  box-shadow: var(--pv-shadow-chrome);
}
.pv-centered-main {
  flex: 1;
  padding: 24px 0 32px;
}
.pv-centered-footer {
  padding: 16px 0;
  border-top: 1px solid var(--pv-line);
}
</style>
