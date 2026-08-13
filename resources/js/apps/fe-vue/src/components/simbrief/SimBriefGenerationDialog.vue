<script setup lang="ts">
import { computed, nextTick, useTemplateRef, watch } from "vue";
import { submitProviderGet, type ProviderSubmission } from "@phpvms/simbrief";

const props = defineProps<{
  failure: string | null;
  flightLabel: string;
  open: boolean;
  requesting: boolean;
  submission: ProviderSubmission | null;
}>();

const emit = defineEmits<{
  "update:open": [open: boolean];
  openPopup: [];
  retry: [];
  returned: [];
  submitted: [];
}>();

const target = "phpvms-simbrief-embedded";
const iframe = useTemplateRef<HTMLIFrameElement>("iframe");
const submitted = computed(() => props.submission !== null);

watch(
  () => props.submission,
  async (submission) => {
    if (!submission || !props.open) return;
    await nextTick();
    if (iframe.value?.name !== target) return;
    submitProviderGet(submission, target);
    emit("submitted");
  },
);

function returnToPlanning() {
  emit("returned");
  emit("update:open", false);
}
</script>

<template>
  <UModal
    :open="open"
    title="Generate OFP"
    :description="`Generate the SimBrief operational flight plan for ${flightLabel}.`"
    :ui="{ content: 'pv-simbrief-generation-surface' }"
    @update:open="emit('update:open', $event)"
  >
    <template #content>
      <section
        class="pv-simbrief-generation"
        aria-label="Generate SimBrief operational flight plan"
      >
        <header class="generation-header">
          <div>
            <p class="pv-eyebrow">SIMBRIEF · GENERATE OFP</p>
            <h2>{{ flightLabel }}</h2>
            <p>Complete the provider flow below, then return here to check for the briefing.</p>
          </div>
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-tabler-x"
            aria-label="Close OFP generation"
            @click="returnToPlanning"
          />
        </header>

        <main class="generation-region">
          <div v-if="requesting" class="generation-loading" role="status" aria-live="polite">
            Preparing the provider flight plan…
          </div>
          <UAlert
            v-if="failure && !submitted"
            color="error"
            variant="subtle"
            icon="i-tabler-alert-circle"
            :description="failure"
          />
          <iframe
            v-if="submitted"
            ref="iframe"
            :name="target"
            title="SimBrief OFP generation"
            class="generation-frame"
          />
          <div v-else-if="!failure" class="generation-loading" role="status" aria-live="polite">
            Waiting to start SimBrief…
          </div>
        </main>

        <footer class="generation-footer">
          <p>
            SimBrief is cross-origin. phpVMS cannot inspect its content or detect when its form is
            complete.
          </p>
          <div class="generation-actions">
            <UButton v-if="failure" color="neutral" variant="soft" @click="emit('retry')"
              >Try again</UButton
            >
            <UButton v-if="submitted" color="neutral" variant="soft" @click="emit('openPopup')"
              >Open in popup</UButton
            >
            <UButton @click="returnToPlanning">Close</UButton>
          </div>
        </footer>
      </section>
    </template>
  </UModal>
</template>

<style scoped>
.pv-simbrief-generation {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: 100%;
  height: min(920px, 94dvh);
  min-width: 0;
  overflow: hidden;
  background: var(--pv-bg);
  color: var(--pv-ink);
}
.generation-header,
.generation-footer {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  background: var(--pv-panel);
  padding: 18px 20px;
}
.generation-header {
  border-bottom: 1px solid var(--pv-line-strong);
}
.generation-footer {
  align-items: center;
  border-top: 1px solid var(--pv-line-strong);
}
.generation-header h2 {
  margin: 3px 0 0;
  color: var(--pv-ink);
  font-size: calc(1.25rem * var(--pv-type-scale));
}
.generation-header p:last-child,
.generation-footer > p {
  margin: 5px 0 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.generation-footer > p {
  max-width: 52ch;
  margin: 0;
  font-size: calc(0.75rem * var(--pv-type-scale));
}
.generation-region {
  min-width: 0;
  min-height: 0;
  padding: 16px;
  background: var(--pv-panel-inset);
}
.generation-frame {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 28rem;
  border: 0;
  background: var(--pv-panel);
}
.generation-loading {
  display: grid;
  place-items: center;
  min-height: 18rem;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.generation-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
@media (max-width: 640px) {
  .generation-header,
  .generation-footer {
    padding-inline: 14px;
  }
  .generation-footer {
    align-items: stretch;
    flex-direction: column;
  }
  .generation-actions :deep(button) {
    flex: 1;
  }
}
</style>

<style>
.pv-simbrief-generation-surface {
  width: min(1280px, 96vw) !important;
  max-width: none !important;
  height: min(920px, 94dvh);
  max-height: 94dvh !important;
  overflow: hidden;
}
@media (max-width: 640px) {
  .pv-simbrief-generation-surface {
    width: 100vw !important;
    height: 100dvh;
    max-height: 100dvh !important;
    border-radius: 0 !important;
  }
}
</style>
