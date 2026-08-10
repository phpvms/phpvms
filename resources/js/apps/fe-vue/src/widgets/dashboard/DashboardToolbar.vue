<script setup lang="ts">
import { shallowRef, useTemplateRef } from "vue";
import { onClickOutside } from "@vueuse/core";
import PvIcon from "@/shared/ui/PvIcon.vue";
import type { WidgetDef } from "./catalog";

defineProps<{ availableWidgets: WidgetDef[]; editing: boolean }>();
const emit = defineEmits<{ addWidget: [id: string]; reset: []; toggleEditing: [] }>();
const addMenuOpen = shallowRef(false);
const addMenu = useTemplateRef<HTMLElement>("addMenu");

onClickOutside(addMenu, () => (addMenuOpen.value = false));

function addWidget(id: string) {
  emit("addWidget", id);
  addMenuOpen.value = false;
}
</script>

<template>
  <div class="toolbar pv-dashboard-toolbar">
    <h2 class="h2">{{ $t("common.dashboard") }}</h2>
    <div class="tools">
      <div v-if="editing" ref="addMenu" class="addwrap">
        <button
          type="button"
          class="btn"
          :disabled="!availableWidgets.length"
          @click="addMenuOpen = !addMenuOpen"
        >
          <svg viewBox="0 0 24 24" class="i" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14" />
          </svg>
          {{ $t("ui.add_widget") }}
        </button>
        <div v-if="addMenuOpen" class="menu">
          <div v-if="!availableWidgets.length" class="menu-empty">
            {{ $t("ui.all_widgets_placed") }}
          </div>
          <button
            v-for="widget in availableWidgets"
            :key="widget.id"
            type="button"
            class="menu-item"
            @click="addWidget(widget.id)"
          >
            <PvIcon :name="widget.icon" :size="15" class="i" />
            {{ widget.title }}
          </button>
        </div>
      </div>
      <button v-if="editing" type="button" class="btn ghost" @click="emit('reset')">
        {{ $t("ui.reset") }}
      </button>
      <button
        type="button"
        class="btn"
        :class="{ primary: editing }"
        @click="emit('toggleEditing')"
      >
        {{ editing ? $t("ui.done") : $t("ui.customize") }}
      </button>
    </div>
  </div>
</template>

<style scoped>
@layer components {
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
    box-shadow: 0 8px 24px rgb(0 0 0 / 12%);
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
}
</style>
