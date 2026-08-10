<script setup lang="ts">
import { computed } from "vue";
import { VueDraggable } from "vue-draggable-plus";
import { resolveValue } from "@/shared/lib/registry";
import { widgetById } from "./catalog";
import { resolveWidget, type ResolvedWidget } from "./resolve";
import type { DashboardLayout } from "./useDashboardLayout";
import WidgetFrame from "./WidgetFrame.vue";

const props = defineProps<{ editing: boolean; pageProps: Record<string, unknown> }>();
const emit = defineEmits<{ removeWidget: [id: string] }>();
const layout = defineModel<DashboardLayout>({ required: true });
const componentCache = new Map<string, ResolvedWidget>();
const dragOptions = { animation: 160, handle: ".handle", ghostClass: "ws-ghost", group: "dash" };

function definition(id: string) {
  return widgetById(id);
}

function baseWidget(id: string) {
  const cached = componentCache.get(id);
  if (cached) return cached;

  const widgetDefinition = definition(id);
  if (!widgetDefinition) return null;

  const resolved = resolveWidget(widgetDefinition);
  componentCache.set(id, resolved);
  return resolved;
}

const resolvedWidgets = computed(() => {
  const widgets: Record<string, ResolvedWidget> = {};
  const ids = new Set([...layout.value.grid, ...layout.value.sidebar]);

  for (const id of ids) {
    const resolved = baseWidget(id);
    if (!resolved) continue;

    const resolvedProps: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(resolved.props)) {
      resolvedProps[key] = resolveValue(value, props.pageProps);
    }
    widgets[id] = { ...resolved, props: resolvedProps };
  }

  return widgets;
});

function spanStyle(id: string) {
  const span = definition(id)?.span ?? 1;
  return { gridColumn: `span ${span} / span ${span}` };
}
</script>

<template>
  <div class="board pv-dashboard-board">
    <VueDraggable v-model="layout.grid" class="zone grid" v-bind="dragOptions" :disabled="!editing">
      <div v-for="id in layout.grid" :key="id" class="cell" :style="spanStyle(id)">
        <WidgetFrame
          :title="definition(id)?.title ?? id"
          :icon="definition(id)?.icon"
          :editing="editing"
          :removable="definition(id)?.removable !== false"
          @remove="emit('removeWidget', id)"
        >
          <component :is="resolvedWidgets[id]?.component" v-bind="resolvedWidgets[id]?.props" />
        </WidgetFrame>
      </div>
    </VueDraggable>

    <VueDraggable
      v-model="layout.sidebar"
      class="zone sidebar"
      v-bind="dragOptions"
      :disabled="!editing"
    >
      <div v-for="id in layout.sidebar" :key="id" class="cell">
        <WidgetFrame
          :title="definition(id)?.title ?? id"
          :icon="definition(id)?.icon"
          :editing="editing"
          :removable="definition(id)?.removable !== false"
          @remove="emit('removeWidget', id)"
        >
          <component :is="resolvedWidgets[id]?.component" v-bind="resolvedWidgets[id]?.props" />
        </WidgetFrame>
      </div>
    </VueDraggable>
  </div>
</template>

<style scoped>
@layer components {
  .board {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .zone {
    display: grid;
    gap: 12px;
    min-height: 60px;
  }
  .zone.grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-content: start;
  }
  .zone.sidebar {
    grid-template-columns: 1fr;
    align-content: start;
  }
  .cell {
    min-width: 0;
  }
  :deep(.ws-ghost) {
    opacity: 0.4;
  }

  @media (min-width: 1100px) {
    .board {
      grid-template-columns: 1fr var(--pv-aside-width, 320px);
      align-items: start;
    }
  }
}
</style>
