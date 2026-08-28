<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from "vue";
import IconX from "~icons/tabler/x";
import UButton from "@nuxt/ui/components/Button.vue";
import UModal from "@nuxt/ui/components/Modal.vue";

const props = defineProps<{
  open: boolean;
  editorUrl: string;
  flightLabel: string;
}>();

const emit = defineEmits<{
  "update:open": [open: boolean];
  returned: [];
}>();

const heading = useTemplateRef<HTMLElement>("heading");
const frame = useTemplateRef<HTMLIFrameElement>("frame");
const loading = defineModel<boolean>("loading", { default: true });

watch(
  () => props.open,
  async (open) => {
    if (!open) return;
    loading.value = true;
    await nextTick();
    heading.value?.focus();
  },
);

function close() {
  emit("update:open", false);
}

function markLoaded() {
  loading.value = false;
}
</script>

<template>
  <UModal
    :open="open"
    title="Edit OFP"
    :description="`Edit the SimBrief operational flight plan for ${flightLabel}.`"
    :dismissible="true"
    :ui="{ content: 'pv-simbrief-editor-surface' }"
    @update:open="emit('update:open', $event)"
  >
    <template #content>
      <section class="pv-simbrief-editor" aria-label="Edit SimBrief operational flight plan">
        <header class="editor-header">
          <div class="editor-context">
            <p class="pv-eyebrow">SIMBRIEF · EDIT OFP</p>
            <h2 ref="heading" tabindex="-1">{{ flightLabel }}</h2>
            <p>
              Changes are made securely in SimBrief and remain subject to its own session and
              controls.
            </p>
          </div>
          <UButton
            color="neutral"
            variant="ghost"
            :icon="IconX"
            aria-label="Close OFP editor"
            @click="close"
          />
        </header>

        <div class="editor-region">
          <div v-if="loading" class="editor-loading" role="status" aria-live="polite">
            <span class="loading-mark" aria-hidden="true" />
            <div>
              <strong>Opening SimBrief editor</strong>
              <p>The editor is loading in a protected embedded window.</p>
            </div>
          </div>
          <p v-else class="editor-status" role="status">
            SimBrief editor loaded. Use the provider controls inside the embedded window.
          </p>
          <iframe
            ref="frame"
            class="editor-frame"
            :src="editorUrl"
            title="SimBrief OFP editor"
            @load="markLoaded"
          />
        </div>

        <footer class="editor-footer">
          <p>
            The embedded editor is operated by SimBrief. phpVMS cannot inspect or control its
            cross-origin content.
          </p>
          <div class="editor-actions">
            <UButton color="neutral" variant="ghost" @click="close">Close</UButton>
            <UButton @click="emit('returned')">Return to briefing / Download updated OFP</UButton>
          </div>
        </footer>
      </section>
    </template>
  </UModal>
</template>

<style scoped>
.pv-simbrief-editor {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: 100%;
  height: min(920px, 94dvh);
  min-width: 0;
  overflow: hidden;
  background: var(--pv-bg);
  color: var(--pv-ink);
}
.editor-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--pv-line-strong);
  background: var(--pv-panel);
  padding: 18px 20px;
}
.editor-context {
  min-width: 0;
}
.editor-context h2 {
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(20px * var(--pv-type-scale));
  outline: none;
}
.editor-context h2:focus-visible {
  outline: 2px solid var(--pv-accent);
  outline-offset: 4px;
  border-radius: var(--pv-radius-sm);
}
.editor-context p:last-child {
  margin: 5px 0 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
  overflow-wrap: anywhere;
}
.editor-region {
  position: relative;
  min-width: 0;
  min-height: 0;
  background: var(--pv-panel-inset);
}
.editor-frame {
  display: block;
  width: 100%;
  height: 100%;
  min-width: 0;
  border: 0;
  background: var(--pv-panel);
}
.editor-loading {
  position: absolute;
  z-index: 1;
  inset: 0;
  display: grid;
  place-content: center;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 12px;
  padding: 24px;
  background: var(--pv-panel-inset);
  color: var(--pv-ink);
}
.editor-loading strong {
  display: block;
  font-size: calc(1rem * var(--pv-type-scale));
}
.editor-loading p {
  margin: 4px 0 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.loading-mark {
  width: 28px;
  height: 28px;
  border: 3px solid var(--pv-line);
  border-top-color: var(--pv-accent);
  border-radius: 50%;
  animation: simbrief-editor-spin 0.8s linear infinite;
}
.editor-status {
  position: absolute;
  z-index: 1;
  inset: auto auto 12px 12px;
  max-width: min(40rem, calc(100% - 24px));
  margin: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-sm);
  background: color-mix(in srgb, var(--pv-panel) 92%, transparent);
  color: var(--pv-ink-dim);
  padding: 8px 10px;
  font-size: calc(0.75rem * var(--pv-type-scale));
}
.editor-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-top: 1px solid var(--pv-line-strong);
  background: var(--pv-panel);
  padding: 14px 20px;
}
.editor-footer > p {
  max-width: 52ch;
  margin: 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.75rem * var(--pv-type-scale));
  overflow-wrap: anywhere;
}
.editor-actions {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
@keyframes simbrief-editor-spin {
  to {
    transform: rotate(360deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .loading-mark {
    animation: none;
  }
}
@media (max-width: 640px) {
  .editor-header,
  .editor-footer {
    padding-inline: 14px;
  }
  .editor-footer {
    align-items: stretch;
    flex-direction: column;
  }
  .editor-actions :deep(button) {
    flex: 1;
  }
}
</style>

<style>
.pv-simbrief-editor-surface {
  width: min(1280px, 96vw) !important;
  max-width: none !important;
  height: min(920px, 94dvh);
  max-height: 94dvh !important;
  overflow: hidden;
}
@media (max-width: 640px) {
  .pv-simbrief-editor-surface {
    width: 100vw !important;
    height: 100dvh;
    max-height: 100dvh !important;
    border-radius: 0 !important;
  }
}
</style>
