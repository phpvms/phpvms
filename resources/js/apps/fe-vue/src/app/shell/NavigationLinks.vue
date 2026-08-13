<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import PvIcon from "@/shared/components/PvIcon.vue";
import type { NavigationDestination } from "./navigation";

const props = defineProps<{
  activeHref: string | null;
  destinations: readonly NavigationDestination[];
}>();

function isActive(href: string) {
  return props.activeHref === href;
}
</script>

<template>
  <nav class="nav pv-navigation-links">
    <div class="micro sect">{{ $t("ui.nav_section") }}</div>
    <component
      :is="destination.spa ? Link : 'a'"
      v-for="destination in destinations"
      :key="destination.href"
      :href="destination.href"
      class="item"
      :class="{ active: isActive(destination.href) }"
      :aria-current="isActive(destination.href) ? 'page' : undefined"
    >
      <PvIcon :name="destination.icon" :size="17" class="i" />
      <span class="lbl">{{ $t(destination.label) }}</span>
    </component>
  </nav>
</template>

<style scoped>
@layer components {
  .nav {
    flex: 1;
    padding: 12px 8px;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .sect {
    padding: 4px 8px 6px;
  }
  .item {
    display: flex;
    align-items: center;
    gap: 10px;
    height: 34px;
    padding: 0 10px;
    border-radius: var(--pv-radius-md);
    color: var(--pv-ink-dim);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
  }
  .item:hover {
    background: var(--pv-hover);
    color: var(--pv-ink);
  }
  .item.active {
    background: var(--pv-accent-soft);
    color: var(--pv-accent);
  }
  .item .i {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
  }
  .lbl {
    flex: 1;
  }
}
</style>
