<script setup lang="ts">
import { computed, reactive, shallowRef } from "vue";
import { router } from "@inertiajs/vue3";
import PvSlot from "@/shared/components/PvSlot.vue";
import { entriesForSlot } from "@/shared/lib/registry";
import { usePvContext } from "@/shared/lib/usePvSlot";
import BriefingRouteGlobe from "@/components/simbrief/BriefingRouteGlobe.vue";
import FlightIdentHeader from "@/components/flights/FlightIdentHeader.vue";
import SimBriefEditorDialog from "@/components/simbrief/SimBriefEditorDialog.vue";
import IconDownload from "~icons/tabler/download";
import UAlert from "@nuxt/ui/components/Alert.vue";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UDrawer from "@nuxt/ui/components/Drawer.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";
import UTabs from "@nuxt/ui/components/Tabs.vue";

const props = defineProps<{ briefing: App.Http.Data.SimBriefBriefingData }>();
const briefing = reactive({ ...props.briefing });
const editorOpen = shallowRef(false);
const downloadsOpen = shallowRef(false);

/** Single source for the slot name — the template binds it and the separator tests it. */
const BRIEFING_ACTIONS_SLOT = "flight.briefing.actions";

/** One tab per top-level OFP section, titled by SimBrief's own bookmarks. */
const textTabs = computed(() =>
  briefing.textSections.map((section, index) => ({
    label: section.title,
    value: String(index),
    html: section.html,
  })),
);
const { registry } = usePvContext();
const hasBriefingActions = computed(
  () => entriesForSlot(registry, BRIEFING_ACTIONS_SLOT).length > 0,
);
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
      <template #description>
        <div class="briefing-identity">
          <FlightIdentHeader
            :flight="briefing.flight.summary"
            :aircraft="`${briefing.aircraft.registration} · ${briefing.aircraft.icaoType}`"
            size="lg"
          />
          <UBadge color="neutral" variant="soft" class="uppercase">Completed briefing</UBadge>
        </div>
      </template>
    </UPageHeader>

    <UPageBody>
      <UAlert v-if="failure" color="error" variant="subtle" :description="failure" />

      <UPageCard title="Route" variant="naked" :ui="{ body: 'grid content-start gap-3' }">
        <BriefingRouteGlobe
          :departure="briefing.flight.departure"
          :arrival="briefing.flight.arrival"
          :planned-fixes="briefing.plannedFixes"
        />
      </UPageCard>

      <div class="briefing-grid">
        <UPageCard
          title="Operational summary"
          variant="outline"
          class="summary-panel"
          :ui="{ body: 'grid content-start gap-3' }"
        >
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
        </UPageCard>

        <UPageCard
          as="aside"
          title="Actions"
          variant="outline"
          class="resources-panel"
          :ui="{ body: 'grid content-start gap-3' }"
        >
          <!-- TEMPORARY: visual marker for the extension point. Remove on request. -->
          <p class="pv-slot-marker">slot: {{ BRIEFING_ACTIONS_SLOT }}</p>
          <PvSlot
            :name="BRIEFING_ACTIONS_SLOT"
            :context="{
              flight: briefing.flight,
              bid: briefing.bid,
              briefing,
              aircraft: briefing.aircraft,
            }"
          />
          <hr v-if="hasBriefingActions" class="slot-separator" />

          <div class="briefing-lifecycle">
            <UButton v-if="briefing.editorUrl" block @click="editorOpen = true">Edit OFP</UButton>
            <UButton
              v-if="briefing.canRegenerate"
              block
              color="neutral"
              variant="soft"
              :loading="regenerating"
              @click="regenerate"
              >Regenerate OFP</UButton
            >
            <UButton
              v-if="briefing.canCancel && !confirmingCancel"
              block
              color="error"
              variant="soft"
              @click="confirmingCancel = true"
              >Cancel unused briefing</UButton
            >
            <UButton
              v-if="briefing.downloads.length"
              block
              color="neutral"
              variant="soft"
              :icon="IconDownload"
              @click="downloadsOpen = true"
              >Downloads</UButton
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
        </UPageCard>
      </div>

      <UPageCard
        title="Text OFP"
        variant="outline"
        class="text-ofp-panel"
        :ui="{ body: 'grid content-start gap-3' }"
      >
        <UTabs
          v-if="textTabs.length"
          :items="textTabs"
          :unmount-on-hide="false"
          size="sm"
          class="ofp-tabs"
        >
          <template #content="{ item }">
            <!-- Sanitized server-side by App\Support\SimBriefPlanHtml (allowlisted
                 tags/attrs, script subtrees dropped, non-http URLs stripped). -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <pre class="briefing-text" v-html="item.html" />
          </template>
        </UTabs>
        <pre v-else class="briefing-text">No text OFP is available.</pre>
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
      </UPageCard>
    </UPageBody>

    <UDrawer
      :open="downloadsOpen"
      direction="right"
      title="Downloads"
      description="Files generated with this SimBrief briefing."
      :handle="false"
      close
      @update:open="downloadsOpen = $event"
    >
      <template #content>
        <section class="downloads-drawer" aria-label="Downloads">
          <p class="section-label">Briefing downloads</p>
          <a
            v-for="download in briefing.downloads"
            :key="download.url"
            class="resource-link"
            :href="download.url"
            target="_blank"
            rel="noopener"
            >{{ download.name }}</a
          >
        </section>
      </template>
    </UDrawer>

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
@layer components {
  .pv-simbrief-briefing {
    min-width: 0;
  }
  .briefing-header {
    padding-bottom: 16px;
  }
  .briefing-identity {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
  }
  .briefing-lifecycle {
    display: grid;
    gap: 8px;
  }
  /* TEMPORARY: extension-point marker. Remove together with the template node. */
  .pv-slot-marker {
    margin: 0;
    border: 1px dashed var(--pv-red);
    border-radius: var(--pv-radius-md);
    background: color-mix(in srgb, var(--pv-red) 8%, var(--pv-panel));
    color: var(--pv-red);
    padding: 8px 10px;
    font-family: var(--pv-font-mono);
    font-size: calc(0.75rem * var(--pv-type-scale));
  }
  .slot-separator {
    margin: 0;
    border: 0;
    border-top: 1px solid var(--pv-line);
  }
  .downloads-drawer {
    display: grid;
    align-content: start;
    gap: 10px;
    padding: 20px;
  }
  .route-panel {
    gap: 10px;
  }
  .briefing-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(18rem, 0.75fr);
    gap: 16px;
  }
  /* Card chrome is UPageCard's; only the min-width guard for grid children remains. */
  .summary-panel,
  .resources-panel,
  .text-ofp-panel {
    min-width: 0;
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
  .briefing-text {
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
  .ofp-tabs {
    min-width: 0;
  }
  /*
   * The OFP body is column-aligned fixed-width text, so it must NOT inherit
   * `.briefing-text`'s pre-wrap/anywhere wrapping — that reflows the fuel and
   * navlog tables into unreadable ragged columns. Scroll sideways instead.
   */
  .ofp-tabs .briefing-text {
    margin-top: 12px;
    white-space: pre;
    overflow-wrap: normal;
  }
  /* v-html content is not in this component's scope — needs :deep(). */
  .ofp-tabs .briefing-text :deep(img) {
    max-width: 100%;
    height: auto;
  }
  .ofp-tabs .briefing-text :deep(h2) {
    margin: 14px 0 4px;
    color: var(--pv-ink);
    font-size: calc(0.875rem * var(--pv-type-scale));
    font-weight: 750;
  }
  .ofp-tabs .briefing-text :deep(a) {
    color: var(--pv-accent);
    text-decoration: underline;
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
    .briefing-lifecycle {
      justify-content: flex-start;
    }
    .briefing-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 390px) {
    .summary-facts {
      grid-template-columns: minmax(0, 1fr);
    }
    .briefing-lifecycle :deep(button) {
      flex: 1;
    }
  }
}
</style>
