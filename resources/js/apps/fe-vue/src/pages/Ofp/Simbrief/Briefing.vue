<script setup lang="ts">
import { reactive, shallowRef } from "vue";
import { router } from "@inertiajs/vue3";
import PvSlot from "@/shared/components/PvSlot.vue";
import SimBriefEditorDialog from "@/components/simbrief/SimBriefEditorDialog.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

const props = defineProps<{ briefing: App.Http.Data.SimBriefBriefingData }>();
const briefing = reactive({ ...props.briefing });
const editorOpen = shallowRef(false);
const cancelling = shallowRef(false);
const confirmingCancel = shallowRef(false);
const regenerating = shallowRef(false);
const syncingEditor = shallowRef(false);
const failure = shallowRef<string | null>(null);

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

async function cancelBriefing() {
  if (cancelling.value || !briefing.canCancel) return;
  cancelling.value = true;
  failure.value = null;
  try {
    const response = await fetch(`/ofp/briefings/${encodeURIComponent(props.briefing.id)}`, {
      method: "DELETE",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
    });
    const result = (await response.json()) as { flightUrl?: string; message?: string };
    if (!response.ok || !result.flightUrl)
      throw new Error(result.message ?? "Briefing could not be cancelled.");
    router.visit(result.flightUrl);
  } catch {
    failure.value = "The briefing could not be cancelled. Check your connection and try again.";
  } finally {
    cancelling.value = false;
    confirmingCancel.value = false;
  }
}

async function regenerate() {
  if (regenerating.value || !briefing.canRegenerate) return;
  regenerating.value = true;
  failure.value = null;
  try {
    const response = await fetch(`/ofp/briefings/${encodeURIComponent(briefing.id)}/regenerate`, {
      method: "POST",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
    });
    const result = (await response.json()) as { planningUrl?: string; message?: string };
    if (!response.ok || !result.planningUrl)
      throw new Error(result.message ?? "Briefing could not be regenerated.");
    router.visit(result.planningUrl);
  } catch {
    failure.value = "The briefing could not be regenerated. Check your connection and try again.";
  } finally {
    regenerating.value = false;
  }
}

async function syncEditor() {
  if (syncingEditor.value) return;
  syncingEditor.value = true;
  failure.value = null;
  try {
    const response = await fetch(`/ofp/briefings/${encodeURIComponent(briefing.id)}/edit-sync`, {
      method: "POST",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken() },
    });
    const result = (await response.json()) as {
      briefing?: App.Http.Data.SimBriefBriefingData;
      message?: string;
    };
    if (!response.ok || !result.briefing)
      throw new Error(result.message ?? "Updated OFP could not be retrieved.");
    Object.assign(briefing, result.briefing);
    editorOpen.value = false;
  } catch {
    failure.value =
      "The updated OFP could not be retrieved yet. Keep editing or try again shortly.";
  } finally {
    syncingEditor.value = false;
  }
}
</script>

<template>
  <UPage class="pv-simbrief-briefing" aria-label="SimBrief briefing">
    <UPageHeader class="briefing-header">
      <div>
        <p class="pv-eyebrow">SIMBRIEF · COMPLETED BRIEFING</p>
        <h1>{{ briefing.flight.summary.callsign }}</h1>
        <p>
          {{ briefing.flight.summary.dpt ?? "—" }} → {{ briefing.flight.summary.arr ?? "—" }} ·
          {{ briefing.aircraft.registration }} · {{ briefing.aircraft.icaoType }}
        </p>
      </div>
      <div class="briefing-actions">
        <UButton v-if="briefing.editorUrl" @click="editorOpen = true">Edit OFP</UButton>
      </div>
    </UPageHeader>

    <p v-if="failure" class="briefing-error" role="alert">{{ failure }}</p>

    <div class="briefing-grid">
      <section class="briefing-panel summary-panel">
        <p class="section-label">Operational summary</p>
        <dl class="summary-facts">
          <div>
            <dt>Flight</dt>
            <dd>{{ briefing.flight.summary.callsign }}</dd>
          </div>
          <div>
            <dt>Aircraft</dt>
            <dd>{{ briefing.aircraft.registration }}</dd>
          </div>
          <div>
            <dt>Scheduled departure</dt>
            <dd>{{ briefing.flight.scheduledDeparture ?? "Not scheduled" }}</dd>
          </div>
          <div>
            <dt>Scheduled arrival</dt>
            <dd>{{ briefing.flight.scheduledArrival ?? "Not scheduled" }}</dd>
          </div>
        </dl>
        <p class="section-label">ATC plan</p>
        <pre class="briefing-text">{{ briefing.atcPlan || "No ATC flight plan published." }}</pre>
        <p class="section-label">Route</p>
        <p class="route">{{ briefing.route || "No route published." }}</p>
      </section>

      <aside class="briefing-panel resources-panel">
        <p class="section-label">Weather</p>
        <dl class="weather-facts">
          <div>
            <dt>Departure METAR</dt>
            <dd>{{ briefing.weather.departureMetar || "Not available" }}</dd>
          </div>
          <div>
            <dt>Departure TAF</dt>
            <dd>{{ briefing.weather.departureTaf || "Not available" }}</dd>
          </div>
          <div>
            <dt>Arrival METAR</dt>
            <dd>{{ briefing.weather.arrivalMetar || "Not available" }}</dd>
          </div>
          <div>
            <dt>Arrival TAF</dt>
            <dd>{{ briefing.weather.arrivalTaf || "Not available" }}</dd>
          </div>
        </dl>

        <template v-if="briefing.downloads.length">
          <p class="section-label">Downloads</p>
          <a
            v-for="download in briefing.downloads"
            :key="download.url"
            class="resource-link"
            :href="download.url"
            target="_blank"
            rel="noopener"
            >{{ download.name }}</a
          >
        </template>
        <template v-if="briefing.prefileLinks">
          <p class="section-label">Network prefile</p>
          <a
            v-for="(url, name) in briefing.prefileLinks"
            v-show="url"
            :key="name"
            class="resource-link"
            :href="url"
            target="_blank"
            rel="noopener"
            >{{ name }}</a
          >
        </template>

        <div class="briefing-lifecycle">
          <UButton
            v-if="briefing.canRegenerate"
            color="neutral"
            variant="soft"
            :loading="regenerating"
            @click="regenerate"
            >Regenerate OFP</UButton
          >
          <UButton
            v-if="briefing.canCancel && !confirmingCancel"
            color="error"
            variant="soft"
            @click="confirmingCancel = true"
            >Cancel unused briefing</UButton
          >
        </div>
        <div v-if="confirmingCancel" class="cancel-confirmation" role="status" aria-live="polite">
          <span>Cancel this unused briefing? This cannot be undone.</span>
          <div>
            <UButton
              color="neutral"
              variant="ghost"
              :disabled="cancelling"
              @click="confirmingCancel = false"
              >Keep briefing</UButton
            ><UButton color="error" :loading="cancelling" @click="cancelBriefing"
              >Confirm cancellation</UButton
            >
          </div>
        </div>
        <PvSlot
          name="simbrief.briefing.actions"
          :context="{
            flight: briefing.flight,
            bid: briefing.bid,
            briefing,
            aircraft: briefing.aircraft,
          }"
        />
      </aside>
    </div>

    <section class="briefing-panel text-ofp-panel">
      <p class="section-label">Text OFP</p>
      <pre class="briefing-text">{{ briefing.textOfp || "No text OFP is available." }}</pre>
      <div v-if="briefing.images.length" class="image-grid">
        <a
          v-for="image in briefing.images"
          :key="image.url"
          :href="image.url"
          target="_blank"
          rel="noopener"
          ><img :src="image.url" :alt="image.name"
        /></a>
      </div>
    </section>

    <SimBriefEditorDialog
      v-if="briefing.editorUrl"
      v-model:open="editorOpen"
      :editor-url="briefing.editorUrl"
      :flight-label="`${briefing.flight.summary.callsign} · ${briefing.flight.summary.dpt ?? '—'} → ${briefing.flight.summary.arr ?? '—'}`"
      @returned="syncEditor"
    />
  </UPage>
</template>

<style scoped>
.pv-simbrief-briefing {
  display: grid;
  min-width: 0;
  gap: 18px;
}
.briefing-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  border-bottom: 1px solid var(--pv-line-strong);
  padding-bottom: 16px;
}
.briefing-header h1 {
  margin: 4px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(1.5rem * var(--pv-type-scale));
}
.briefing-header p:last-child {
  margin: 5px 0 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.briefing-actions,
.briefing-lifecycle {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
.briefing-error {
  margin: 0;
  border: 1px solid color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
  border-radius: var(--pv-radius-md);
  background: color-mix(in srgb, var(--pv-red) 8%, var(--pv-panel));
  color: var(--pv-red);
  padding: 11px 12px;
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.briefing-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.45fr) minmax(18rem, 0.75fr);
  gap: 16px;
}
.briefing-panel {
  display: grid;
  align-content: start;
  min-width: 0;
  gap: 12px;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-lg);
  background: var(--pv-panel);
  padding: 18px;
}
.section-label {
  margin: 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.75rem * var(--pv-type-scale));
  font-weight: 750;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.summary-facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}
.summary-facts div,
.weather-facts div {
  min-width: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  padding: 10px;
}
dt {
  color: var(--pv-ink-dim);
  font-size: calc(0.75rem * var(--pv-type-scale));
  text-transform: uppercase;
}
dd {
  overflow-wrap: anywhere;
  margin: 4px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.briefing-text,
.route {
  overflow: auto;
  margin: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  color: var(--pv-ink);
  padding: 12px;
  font-family: var(--pv-font-mono);
  font-size: calc(0.875rem * var(--pv-type-scale));
  line-height: 1.55;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.weather-facts {
  display: grid;
  gap: 8px;
  margin: 0;
}
.resource-link {
  overflow-wrap: anywhere;
  color: var(--pv-accent);
  font-size: calc(0.875rem * var(--pv-type-scale));
  text-decoration: none;
}
.resource-link:hover {
  text-decoration: underline;
}
.cancel-confirmation {
  display: grid;
  gap: 9px;
  border: 1px solid color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
  border-radius: var(--pv-radius-md);
  background: color-mix(in srgb, var(--pv-red) 8%, var(--pv-panel));
  color: var(--pv-ink);
  padding: 10px;
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.cancel-confirmation > div {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.text-ofp-panel {
  min-height: 18rem;
}
.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: 10px;
}
.image-grid img {
  display: block;
  width: 100%;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
}
@media (max-width: 720px) {
  .briefing-header {
    flex-direction: column;
  }
  .briefing-actions,
  .briefing-lifecycle {
    justify-content: flex-start;
  }
  .briefing-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 390px) {
  .briefing-panel {
    padding: 14px;
  }
  .summary-facts {
    grid-template-columns: minmax(0, 1fr);
  }
  .briefing-actions :deep(button),
  .briefing-lifecycle :deep(button) {
    flex: 1;
  }
}
</style>
