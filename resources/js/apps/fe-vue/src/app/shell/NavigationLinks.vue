<script setup lang="ts">
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import PvIcon from "@/shared/components/PvIcon.vue";
import { menuPath, type NavigationMenuItem } from "./navigation";

const props = defineProps<{
  activePath: string | null;
  items: readonly NavigationMenuItem[];
}>();

interface Row {
  key: string;
  item: NavigationMenuItem;
  depth: number;
  active: boolean;
}

/** The menu tree flattened in render order, children indented under parents. */
const rows = computed<Row[]>(() => {
  const out: Row[] = [];

  const walk = (entries: readonly NavigationMenuItem[], depth: number, prefix: string): void => {
    entries.forEach((item, index) => {
      const key = `${prefix}${index}`;
      const path = menuPath(item.url);

      out.push({ key, item, depth, active: path !== null && path === props.activePath });
      walk(item.children, depth + 1, `${key}.`);
    });
  };

  walk(props.items, 0, "");

  return out;
});
</script>

<template>
  <nav class="nav pv-navigation-links">
    <div class="micro sect">{{ $t("ui.nav_section") }}</div>
    <template v-for="row in rows" :key="row.key">
      <span
        v-if="row.item.url === ''"
        class="item group"
        :style="{ paddingInlineStart: `${10 + row.depth * 16}px` }"
      >
        <PvIcon v-if="row.item.icon" :name="row.item.icon" :size="17" class="i" />
        <span class="lbl">{{ row.item.label }}</span>
      </span>
      <component
        :is="row.item.spa && !row.item.new_window ? Link : 'a'"
        v-else
        :href="row.item.url"
        :target="row.item.new_window ? '_blank' : undefined"
        :rel="row.item.new_window ? 'noopener noreferrer' : undefined"
        class="item"
        :class="{ active: row.active }"
        :style="{ paddingInlineStart: `${10 + row.depth * 16}px` }"
        :aria-current="row.active ? 'page' : undefined"
      >
        <PvIcon v-if="row.item.icon" :name="row.item.icon" :size="17" class="i" />
        <span class="lbl">{{ row.item.label }}</span>
      </component>
    </template>
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
  .item.group {
    color: var(--pv-ink-dim);
    cursor: default;
  }
  .item.group:hover {
    background: none;
  }
}
</style>
