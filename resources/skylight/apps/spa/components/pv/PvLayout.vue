<script setup lang="ts">
import { computed, useSlots } from 'vue'

/**
 * CSS-grid application shell with named slots. The left `navigation` rail spans
 * full height; `header` pins the top; `main` scrolls; `aside`/`footer` are
 * optional and only claim grid tracks when filled.
 *
 * The grid template is token-driven (`--pv-nav-width`, `--pv-aside-width`,
 * `--pv-header-height`) and can be fully remapped at runtime via
 * `--pv-page-areas` with no rebuild.
 */
withDefaults(defineProps<{ minWidth?: string }>(), { minWidth: '1024px' })

const slots = useSlots()
const hasAside = computed(() => !!slots.aside)
const hasFooter = computed(() => !!slots.footer)
</script>

<template>
  <div
    class="pv-layout"
    :class="{ 'has-aside': hasAside, 'has-footer': hasFooter }"
    :style="{ minWidth }"
  >
    <nav class="pv-region-nav" role="navigation" aria-label="Primary">
      <slot name="navigation" />
    </nav>

    <header class="pv-region-header" role="banner">
      <slot name="header" />
    </header>

    <main class="pv-region-main" role="main">
      <slot name="main" />
      <slot />
    </main>

    <aside v-if="hasAside" class="pv-region-aside">
      <slot name="aside" />
    </aside>

    <footer v-if="hasFooter" class="pv-region-footer">
      <slot name="footer" />
    </footer>
  </div>
</template>

<style scoped>
.pv-layout {
  display: grid;
  height: 100vh;
  grid-template-columns: var(--pv-nav-width, 68px) 1fr;
  grid-template-rows: var(--pv-header-height, 48px) 1fr;
  grid-template-areas:
    'nav header'
    'nav main';
  background: var(--pv-bg);
}
.pv-layout.has-aside {
  grid-template-columns: var(--pv-nav-width, 68px) 1fr var(--pv-aside-width, 240px);
  grid-template-areas:
    'nav header header'
    'nav main aside';
}
.pv-layout.has-footer {
  grid-template-rows: var(--pv-header-height, 48px) 1fr auto;
  grid-template-areas:
    'nav header'
    'nav main'
    'nav footer';
}
.pv-layout.has-aside.has-footer {
  grid-template-areas:
    'nav header header'
    'nav main aside'
    'nav footer footer';
}

.pv-region-nav {
  grid-area: nav;
  background: var(--pv-panel);
  border-right: 1px solid var(--pv-line);
}
.pv-region-header {
  grid-area: header;
  background: var(--pv-panel);
  border-bottom: 1px solid var(--pv-line);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
}
.pv-region-main {
  grid-area: main;
  overflow-y: auto;
  padding: 24px 28px 32px;
}
.pv-region-aside {
  grid-area: aside;
  overflow-y: auto;
  border-left: 1px solid var(--pv-line);
  background: var(--pv-panel);
  padding: 20px;
}
.pv-region-footer {
  grid-area: footer;
}

/* Themed thin scrollbar for the main deck. */
.pv-region-main::-webkit-scrollbar {
  width: 6px;
}
.pv-region-main::-webkit-scrollbar-track {
  background: var(--pv-bg);
}
.pv-region-main::-webkit-scrollbar-thumb {
  background: var(--pv-line);
  border-radius: 3px;
}
.pv-region-main::-webkit-scrollbar-thumb:hover {
  background: var(--pv-ink-dim);
}
</style>
