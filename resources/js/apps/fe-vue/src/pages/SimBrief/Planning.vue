<script setup lang="ts">
import { onBeforeUnmount } from "vue";
import { useSimBriefAttempt } from "@/features/simbrief/useSimBriefAttempt";

const props = defineProps<{ planning: App.Http.Data.SimBriefPlanningData }>();
const attempt = useSimBriefAttempt(props.planning);
onBeforeUnmount(attempt.dispose);
</script>

<template>
  <section class="pv-simbrief-planning" aria-label="SimBrief planning">
    <header class="planning-header">
      <div>
        <p class="pv-eyebrow">SIMBRIEF · FLIGHT PLANNING</p>
        <h1>{{ planning.flight.summary.callsign }}</h1>
        <p>
          {{ planning.flight.summary.dpt ?? "—" }} → {{ planning.flight.summary.arr ?? "—" }} ·
          {{ planning.aircraft.registration }} · {{ planning.aircraft.icaoType }}
        </p>
      </div>
      <span class="attempt-state" :data-state="attempt.state.value">{{
        attempt.state.value.replaceAll("-", " ")
      }}</span>
    </header>

    <div class="planning-grid">
      <section class="planning-panel" aria-labelledby="planning-route">
        <p id="planning-route" class="section-label">Route and schedule</p>
        <dl class="facts">
          <div>
            <dt>Scheduled departure</dt>
            <dd>{{ planning.flight.scheduledDeparture ?? "Not scheduled" }}</dd>
          </div>
          <div>
            <dt>Scheduled arrival</dt>
            <dd>{{ planning.flight.scheduledArrival ?? "Not scheduled" }}</dd>
          </div>
          <div>
            <dt>Alternate</dt>
            <dd>{{ planning.providerFields.altn ?? "AUTO" }}</dd>
          </div>
          <div>
            <dt>Cruise level</dt>
            <dd>{{ planning.providerFields.fl ?? "Not set" }}</dd>
          </div>
        </dl>
        <p class="route">{{ planning.providerFields.route || "No route published" }}</p>
      </section>

      <aside class="planning-panel action-panel" aria-label="Generate SimBrief plan">
        <p class="section-label">Ready for SimBrief</p>
        <h2>Review complete</h2>
        <p>
          Generation opens SimBrief in a separate window. Keep this page open while the provider
          prepares the operational flight plan.
        </p>
        <p class="muted">
          This uses a server-owned planning attempt. Your stored SimBrief username is not required.
        </p>

        <p
          v-if="attempt.state.value === 'waiting-for-popup'"
          class="attempt-message"
          role="status"
          aria-live="polite"
        >
          SimBrief is open in a separate window. When it closes, phpVMS will check for the completed
          briefing.
        </p>
        <p
          v-else-if="attempt.state.value === 'polling'"
          class="attempt-message"
          role="status"
          aria-live="polite"
        >
          Checking for the completed OFP ({{ attempt.pollAttempts.value }}/8)…
        </p>
        <p v-else-if="attempt.failure.value" class="attempt-error" role="alert">
          {{ attempt.failure.value }}
        </p>

        <div class="planning-actions">
          <UButton
            :loading="attempt.state.value === 'requesting-code'"
            :disabled="!['ready', 'error'].includes(attempt.state.value)"
            @click="attempt.generate"
            >Generate SimBrief</UButton
          >
          <UButton
            v-if="attempt.state.value === 'error'"
            color="neutral"
            variant="soft"
            @click="attempt.retryPoll"
            >Check for completed OFP</UButton
          >
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.pv-simbrief-planning {
  display: grid;
  min-width: 0;
  gap: 18px;
}
.planning-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  border-bottom: 1px solid var(--pv-line-strong);
  padding-bottom: 16px;
}
.planning-header h1 {
  margin: 4px 0 0;
  color: var(--pv-ink);
  font-family: var(--pv-font-mono);
  font-size: calc(1.5rem * var(--pv-type-scale));
}
.planning-header p:last-child {
  margin: 5px 0 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
}
.attempt-state {
  flex: 0 0 auto;
  border: 1px solid var(--pv-line-strong);
  border-radius: var(--pv-radius-full);
  color: var(--pv-ink-dim);
  padding: 5px 9px;
  font-size: calc(0.75rem * var(--pv-type-scale));
  font-weight: 750;
  text-transform: uppercase;
}
.attempt-state[data-state="complete"] {
  color: var(--pv-green);
}
.attempt-state[data-state="error"] {
  color: var(--pv-red);
}
.planning-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.45fr) minmax(18rem, 0.75fr);
  gap: 16px;
}
.planning-panel {
  min-width: 0;
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
.facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 16px 0;
}
.facts div {
  min-width: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  padding: 11px;
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
.route {
  overflow-wrap: anywhere;
  margin: 0;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  color: var(--pv-ink);
  padding: 12px;
  font-family: var(--pv-font-mono);
  font-size: calc(0.875rem * var(--pv-type-scale));
  line-height: 1.55;
}
.action-panel {
  display: grid;
  align-content: start;
  gap: 13px;
}
.action-panel h2 {
  margin: 2px 0 0;
  color: var(--pv-ink);
  font-size: calc(1rem * var(--pv-type-scale));
}
.action-panel > p {
  margin: 0;
  color: var(--pv-ink-dim);
  font-size: calc(0.875rem * var(--pv-type-scale));
  line-height: 1.5;
}
.muted {
  font-size: calc(0.75rem * var(--pv-type-scale)) !important;
}
.attempt-message,
.attempt-error {
  border: 1px solid var(--pv-line-strong);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel-inset);
  color: var(--pv-ink);
  padding: 10px;
}
.attempt-error {
  border-color: color-mix(in srgb, var(--pv-red) 45%, var(--pv-line));
  color: var(--pv-red);
}
.planning-actions {
  display: grid;
  gap: 8px;
  margin-top: 4px;
}
@media (max-width: 720px) {
  .planning-header {
    flex-direction: column;
  }
  .planning-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 390px) {
  .planning-panel {
    padding: 14px;
  }
  .facts {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
