<script setup lang="ts">
import { computed, useSlots, useTemplateRef } from "vue";
import { useResponsiveNavigation } from "./useResponsiveNavigation";

/**
 * CSS-grid application shell with named slots. The left `navigation` rail spans
 * full height; `header` pins the top; `main` scrolls; `aside`/`footer` are
 * optional and only claim grid tracks when filled.
 *
 * The grid template is token-driven (`--pv-nav-width`, `--pv-aside-width`,
 * `--pv-header-height`) and can be fully remapped at runtime via
 * `--pv-page-areas` with no rebuild.
 */
const slots = useSlots();
const hasAside = computed(() => !!slots.aside);
const hasFooter = computed(() => !!slots.footer);
const navigationRegion = useTemplateRef<HTMLElement>("navigationRegion");
const navigationToggle = useTemplateRef<HTMLButtonElement>("navigationToggle");
const {
  isMobile: isMobileNavigation,
  isOpen: isNavigationOpen,
  toggle: toggleNavigation,
  close: closeNavigation,
  closeFromNavigation: handleNavigationClick,
} = useResponsiveNavigation({ navigationRegion, navigationToggle });
</script>

<template>
  <div
    class="pv-layout"
    :class="{ 'has-aside': hasAside, 'has-footer': hasFooter }"
    @keydown.esc="closeNavigation()"
  >
    <nav
      id="pv-primary-navigation"
      ref="navigationRegion"
      class="pv-region-nav"
      :class="{ 'is-open': isNavigationOpen }"
      role="navigation"
      aria-label="Primary"
      :aria-hidden="isMobileNavigation && !isNavigationOpen ? 'true' : undefined"
      :inert="isMobileNavigation && !isNavigationOpen"
      @click="handleNavigationClick"
    >
      <slot name="navigation" />
    </nav>

    <div
      v-if="isMobileNavigation && isNavigationOpen"
      class="pv-nav-backdrop"
      aria-hidden="true"
      @click="closeNavigation()"
    />

    <header class="pv-region-header" role="banner">
      <button
        ref="navigationToggle"
        class="pv-mobile-nav-toggle"
        type="button"
        aria-controls="pv-primary-navigation"
        :aria-expanded="isNavigationOpen"
        :aria-label="isNavigationOpen ? 'Close navigation' : 'Open navigation'"
        @click="toggleNavigation"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16" />
        </svg>
      </button>
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
@layer components {
  .pv-layout {
    display: grid;
    height: 100vh;
    height: 100dvh;
    min-width: 0;
    grid-template-columns: var(--pv-nav-width, 68px) 1fr;
    grid-template-rows: var(--pv-header-height, 48px) 1fr;
    grid-template-areas:
      "nav header"
      "nav main";
    background: var(--pv-bg);
  }
  .pv-layout.has-aside {
    grid-template-columns: var(--pv-nav-width, 68px) 1fr var(--pv-aside-width, 240px);
    grid-template-areas:
      "nav header header"
      "nav main aside";
  }
  .pv-layout.has-footer {
    grid-template-rows: var(--pv-header-height, 48px) 1fr auto;
    grid-template-areas:
      "nav header"
      "nav main"
      "nav footer";
  }
  .pv-layout.has-aside.has-footer {
    grid-template-areas:
      "nav header header"
      "nav main aside"
      "nav footer footer";
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
  .pv-mobile-nav-toggle,
  .pv-nav-backdrop {
    display: none;
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

  @media (max-width: 1023px) {
    .pv-layout,
    .pv-layout.has-aside,
    .pv-layout.has-footer,
    .pv-layout.has-aside.has-footer {
      grid-template-columns: minmax(0, 1fr);
      grid-template-rows: var(--pv-header-height, 56px) minmax(0, 1fr);
      grid-template-areas:
        "header"
        "main";
    }
    .pv-layout.has-aside {
      grid-template-rows: var(--pv-header-height, 56px) minmax(0, 1fr) auto;
      grid-template-areas:
        "header"
        "main"
        "aside";
    }
    .pv-layout.has-footer {
      grid-template-rows: var(--pv-header-height, 56px) minmax(0, 1fr) auto;
      grid-template-areas:
        "header"
        "main"
        "footer";
    }
    .pv-layout.has-aside.has-footer {
      grid-template-rows: var(--pv-header-height, 56px) minmax(0, 1fr) auto auto;
      grid-template-areas:
        "header"
        "main"
        "aside"
        "footer";
    }
    .pv-region-nav {
      position: fixed;
      inset: 0 auto 0 0;
      z-index: 50;
      width: min(var(--pv-nav-width, 240px), calc(100vw - 48px));
      visibility: hidden;
      transform: translateX(-100%);
      box-shadow: var(--pv-shadow-lg, 8px 0 32px rgb(15 23 42 / 18%));
      transition:
        transform 180ms ease,
        visibility 180ms;
    }
    .pv-region-nav.is-open {
      visibility: visible;
      transform: translateX(0);
    }
    .pv-nav-backdrop {
      position: fixed;
      inset: 0;
      z-index: 40;
      display: block;
      background: rgb(15 23 42 / 45%);
    }
    .pv-region-header {
      min-width: 0;
      gap: 8px;
      padding: 0 12px;
    }
    .pv-mobile-nav-toggle {
      width: 44px;
      height: 44px;
      flex: 0 0 44px;
      display: grid;
      place-items: center;
      border: 0;
      border-radius: var(--pv-radius-md);
      background: transparent;
      color: var(--pv-ink-dim);
      cursor: pointer;
    }
    .pv-mobile-nav-toggle:hover {
      background: var(--pv-hover);
      color: var(--pv-ink);
    }
    .pv-mobile-nav-toggle:focus-visible {
      outline: 2px solid var(--pv-accent);
      outline-offset: 2px;
    }
    .pv-mobile-nav-toggle svg {
      width: 20px;
      height: 20px;
      fill: none;
      stroke: currentColor;
      stroke-linecap: round;
      stroke-width: 1.8;
    }
    .pv-region-main {
      min-width: 0;
      padding: 20px 16px 28px;
    }
    .pv-region-aside {
      max-height: 40dvh;
      border-top: 1px solid var(--pv-line);
      border-left: 0;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .pv-region-nav {
      transition: none;
    }
  }
}
</style>
