<script setup lang="ts">
import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import { VueDraggable } from "vue-draggable-plus";
import { onClickOutside } from "@vueuse/core";
import PvApp from "@/components/pv/PvApp.vue";
import PvIcon from "@/components/pv/PvIcon.vue";
import WidgetFrame from "@/components/pv/WidgetFrame.vue";
import { useDashboardLayout } from "@/composables/useDashboardLayout";
import { widgetById } from "@/lib/widgets/catalog";
import { resolveWidget } from "@/components/widgets/resolve";
import { resolveValue } from "@/lib/registry";

/**
 * Dashboard — a configurable, draggable widget board. Widgets come from the
 * catalog; the pilot's layout (which widgets, which zone, what order) is
 * persisted and edited in place: toggle Customize → drag between zones, add via
 * the menu, remove per card. See useDashboardLayout + lib/widgets/catalog.
 */
defineOptions({ layout: PvApp });

const page = usePage();
const name = computed(() => (page.props.name as string) ?? "Pilot");
const rank = computed(() => page.props.rank as { from: string } | null);
const station = computed(() => (page.props.currentAirport as string | null) ?? null);
const onLeave = computed(() => page.props.onLeave as boolean);
const initials = computed(() =>
  name.value
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);

const { layout, editing, availableToAdd, addWidget, removeWidget, resetLayout, toggleEdit } =
  useDashboardLayout();

const def = (id: string) => widgetById(id);
// Memoize per id so async (module) components aren't re-created on every render.
const resolveCache = new Map<string, ReturnType<typeof resolveWidget>>();
const resolve = (id: string) => {
  const cached = resolveCache.get(id);
  if (cached) return cached;
  const d = widgetById(id);
  if (!d) return null;
  const r = resolveWidget(d);
  // Resolve any `@`-prefixed prop refs against the live page DTO props, exactly
  // like PvSlot does for slot entries. This lets a serializable (e.g. addon,
  // ESM) widget declare `props: { icao: '@currentAirport' }` and receive the
  // live host value without importing inertia/usePage itself. Static props pass
  // through untouched.
  const props: Record<string, unknown> = {};
  for (const [key, raw] of Object.entries(r.props)) {
    props[key] = resolveValue(raw, page.props as Record<string, unknown>);
  }
  const resolved = { ...r, props };
  resolveCache.set(id, resolved);
  return resolved;
};
const spanStyle = (id: string) => ({
  gridColumn: `span ${def(id)?.span ?? 1} / span ${def(id)?.span ?? 1}`,
});

const addOpen = ref(false);
const addRef = ref<HTMLElement | null>(null);
onClickOutside(addRef, () => (addOpen.value = false));
function add(id: string) {
  addWidget(id);
  addOpen.value = false;
}

const dragOpts = { animation: 160, handle: ".handle", ghostClass: "ws-ghost", group: "dash" };
</script>

<template>
  <!-- Pilot header -->
  <section class="pilot" aria-label="Pilot">
    <div class="avatar">{{ initials }}</div>
    <div class="who">
      <div class="line1">
        <h1 class="name">{{ name }}</h1>
        <span v-if="rank" class="rankchip">{{ rank.from }}</span>
        <span v-if="onLeave" class="leavechip">{{ $t("skylight.on_leave") }}</span>
      </div>
      <div class="line2">
        <span v-if="station" class="loc">
          <svg viewBox="0 0 24 24" class="pin" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z" />
            <circle cx="12" cy="10" r="2.5" />
          </svg>
          {{ station }}
        </span>
      </div>
    </div>
  </section>

  <!-- Toolbar -->
  <div class="toolbar">
    <h2 class="h2">{{ $t("common.dashboard") }}</h2>
    <div class="tools">
      <div v-if="editing" ref="addRef" class="addwrap">
        <button
          type="button"
          class="btn"
          :disabled="!availableToAdd.length"
          @click="addOpen = !addOpen"
        >
          <svg viewBox="0 0 24 24" class="i" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14" />
          </svg>
          {{ $t("skylight.add_widget") }}
        </button>
        <div v-if="addOpen" class="menu">
          <div v-if="!availableToAdd.length" class="menu-empty">
            {{ $t("skylight.all_widgets_placed") }}
          </div>
          <button
            v-for="w in availableToAdd"
            :key="w.id"
            type="button"
            class="menu-item"
            @click="add(w.id)"
          >
            <PvIcon :name="w.icon" :size="15" class="i" />
            {{ w.title }}
          </button>
        </div>
      </div>
      <button v-if="editing" type="button" class="btn ghost" @click="resetLayout">
        {{ $t("skylight.reset") }}
      </button>
      <button type="button" class="btn" :class="{ primary: editing }" @click="toggleEdit">
        {{ editing ? $t("skylight.done") : $t("skylight.customize") }}
      </button>
    </div>
  </div>

  <!-- Board -->
  <div class="board">
    <VueDraggable v-model="layout.grid" class="zone grid" v-bind="dragOpts" :disabled="!editing">
      <div v-for="id in layout.grid" :key="id" class="cell" :style="spanStyle(id)">
        <WidgetFrame
          :title="def(id)?.title ?? id"
          :icon="def(id)?.icon"
          :editing="editing"
          :removable="def(id)?.removable !== false"
          @remove="removeWidget(id)"
        >
          <component :is="resolve(id)?.component" v-bind="resolve(id)?.props" />
        </WidgetFrame>
      </div>
    </VueDraggable>

    <VueDraggable
      v-model="layout.sidebar"
      class="zone sidebar"
      v-bind="dragOpts"
      :disabled="!editing"
    >
      <div v-for="id in layout.sidebar" :key="id" class="cell">
        <WidgetFrame
          :title="def(id)?.title ?? id"
          :icon="def(id)?.icon"
          :editing="editing"
          :removable="def(id)?.removable !== false"
          @remove="removeWidget(id)"
        >
          <component :is="resolve(id)?.component" v-bind="resolve(id)?.props" />
        </WidgetFrame>
      </div>
    </VueDraggable>
  </div>
</template>

<style scoped>
.pilot {
  display: flex;
  align-items: center;
  gap: 14px;
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-xl);
  padding: 16px 18px;
  margin-bottom: 16px;
}
.avatar {
  width: 48px;
  height: 48px;
  border-radius: var(--pv-radius-lg);
  background: color-mix(in srgb, var(--pv-accent) 14%, var(--pv-panel));
  color: var(--pv-accent);
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 16px;
  flex-shrink: 0;
}
.line1 {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.name {
  font-size: 18px;
  font-weight: 600;
  color: var(--pv-ink);
}
.rankchip {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--pv-radius-full);
  color: var(--pv-accent);
  background: var(--pv-accent-soft);
}
.leavechip {
  font-size: 11px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: var(--pv-radius-full);
  color: var(--pv-amber);
  background: color-mix(in srgb, var(--pv-amber) 12%, transparent);
}
.line2 {
  margin-top: 4px;
}
.loc {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--pv-ink-dim);
  font-family: var(--pv-font-mono);
}
.pin {
  width: 13px;
  height: 13px;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.h2 {
  font-size: 15px;
  font-weight: 600;
  color: var(--pv-ink);
}
.tools {
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 32px;
  padding: 0 12px;
  font-size: 12px;
  font-weight: 500;
  color: var(--pv-ink);
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  cursor: pointer;
}
.btn:hover {
  background: var(--pv-hover);
}
.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn .i {
  width: 15px;
  height: 15px;
}
.btn.primary {
  background: var(--pv-accent);
  border-color: var(--pv-accent);
  color: #fff;
}
.btn.ghost {
  color: var(--pv-ink-dim);
}
.addwrap {
  position: relative;
}
.menu {
  position: absolute;
  right: 0;
  top: calc(100% + 6px);
  z-index: 40;
  min-width: 200px;
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-lg);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  padding: 4px;
}
.menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  height: 32px;
  padding: 0 8px;
  font-size: 12px;
  color: var(--pv-ink);
  border-radius: var(--pv-radius-sm);
  cursor: pointer;
}
.menu-item:hover {
  background: var(--pv-hover);
}
.menu-item .i {
  width: 15px;
  height: 15px;
  color: var(--pv-ink-dim);
}
.menu-empty {
  font-size: 12px;
  color: var(--pv-ink-dim);
  padding: 8px;
}

.board {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}
@media (min-width: 1100px) {
  .board {
    grid-template-columns: 1fr var(--pv-aside-width, 320px);
    align-items: start;
  }
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
</style>
