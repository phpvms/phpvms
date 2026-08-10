<script setup lang="ts">
/**
 * Card chrome around any dashboard widget. In edit mode it reveals a drag handle
 * (the SortableJS `handle`) and a remove control. The body is a default slot so
 * any catalog widget drops in unchanged.
 */
import PvIcon from "@/shared/ui/PvIcon.vue";

withDefaults(
  defineProps<{ title: string; icon?: string; editing?: boolean; removable?: boolean }>(),
  { icon: "", editing: false, removable: true },
);
defineEmits<{ remove: [] }>();
</script>

<template>
  <section class="frame" :class="{ editing }">
    <header class="head">
      <span v-if="editing" class="handle" aria-label="Drag to reorder" title="Drag">
        <svg viewBox="0 0 24 24" class="ic" fill="currentColor">
          <circle cx="9" cy="6" r="1.4" />
          <circle cx="15" cy="6" r="1.4" />
          <circle cx="9" cy="12" r="1.4" />
          <circle cx="15" cy="12" r="1.4" />
          <circle cx="9" cy="18" r="1.4" />
          <circle cx="15" cy="18" r="1.4" />
        </svg>
      </span>
      <PvIcon v-if="icon" :name="icon" :size="15" class="ic tint" />
      <h3 class="title">{{ title }}</h3>
      <div class="spacer" />
      <button
        v-if="editing && removable"
        type="button"
        class="rm"
        aria-label="Remove widget"
        @click="$emit('remove')"
      >
        <svg viewBox="0 0 24 24" class="ic" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 6 6 18M6 6l12 12" />
        </svg>
      </button>
    </header>
    <div class="body">
      <slot />
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .frame {
    background: var(--pv-panel);
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-xl);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-width: 0;
  }
  .frame.editing {
    outline: 1px dashed color-mix(in srgb, var(--pv-accent) 40%, var(--pv-line));
    outline-offset: 2px;
  }
  .head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    border-bottom: 1px solid var(--pv-line);
  }
  .title {
    font-size: 12px;
    font-weight: 600;
    color: var(--pv-ink);
  }
  .ic {
    width: 15px;
    height: 15px;
    color: var(--pv-ink-faint);
  }
  .ic.tint {
    color: var(--pv-ink-dim);
  }
  .spacer {
    flex: 1;
  }
  .handle {
    display: inline-flex;
    cursor: grab;
    color: var(--pv-ink-faint);
  }
  .handle:active {
    cursor: grabbing;
  }
  .rm {
    display: inline-flex;
    padding: 2px;
    border-radius: var(--pv-radius-sm);
    color: var(--pv-ink-dim);
  }
  .rm:hover {
    color: var(--pv-red);
    background: color-mix(in srgb, var(--pv-red) 10%, transparent);
  }
  .body {
    padding: 12px;
    flex: 1;
  }
}
</style>
