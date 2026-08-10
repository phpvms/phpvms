<script setup lang="ts">
import { computed } from "vue";
import type { AppUser } from "@/app/shell/useAppChrome";

const props = defineProps<{
  dashboard: App.Http.Data.DashboardData;
  initials: string;
  user: AppUser | null;
}>();

const rank = computed(() => props.dashboard.rank);
const rankHours = computed(() => {
  const current = rank.value?.currentHours;
  const target = rank.value?.targetHours;

  return `${current?.toLocaleString() ?? "—"} / ${target?.toLocaleString() ?? "—"} hrs`;
});
const promotion = computed(() => {
  if (!rank.value) return "Rank target unavailable";
  if (rank.value.to === null) return "Highest rank reached";
  return rank.value.hoursRemaining === null
    ? "Rank target unavailable"
    : `${rank.value.hoursRemaining.toLocaleString()} hrs to promotion`;
});
const status = computed(
  () =>
    props.dashboard.state?.label ?? (props.dashboard.onLeave ? "On leave" : "Status unavailable"),
);
const landingRate = computed(() =>
  props.dashboard.averageLandingRate === null
    ? "—"
    : `${String(props.dashboard.averageLandingRate).replace("-", "−")} fpm`,
);
const metrics = computed(() => [
  { label: "Flights", value: props.dashboard.flights?.toLocaleString() ?? "—" },
  { label: "Hours", value: props.dashboard.flightTimeMinutes ?? "—" },
  { label: "Transfer", value: props.dashboard.transferTimeMinutes ?? "—" },
  { label: "Balance", value: props.dashboard.balance?.formatted ?? "—" },
  { label: "Pilot score", value: props.dashboard.pilotScore?.toLocaleString() ?? "—" },
  {
    label: "On-time",
    value: props.dashboard.onTimePercentage === null ? "—" : `${props.dashboard.onTimePercentage}%`,
  },
  { label: "Avg landing", value: landingRate.value },
]);
</script>

<template>
  <section class="pv-dashboard-pilot" aria-label="Pilot summary">
    <div class="summary">
      <div class="identity">
        <img v-if="user?.avatar" class="avatar" :src="user.avatar" alt="" />
        <span v-else class="avatar fallback" aria-hidden="true">{{ initials }}</span>
        <div class="identity-copy">
          <div class="identity-title">
            <h1>{{ user?.name ?? "Pilot" }}</h1>
            <span class="status" :data-c="dashboard.state.color">{{ status }}</span>
          </div>
          <p>
            <span>{{ user?.ident ?? "—" }}</span>
            <span aria-hidden="true"> · </span>
            <span>{{ user?.callsign ?? "—" }}</span>
            <span aria-hidden="true"> · </span>
            <span>At {{ dashboard.currentAirport ?? "—" }}</span>
          </p>
        </div>
      </div>

      <div class="rank" aria-label="Rank progress">
        <div class="rank-heading">
          <span class="micro">Rank progress</span>
          <span>
            {{ rank?.from ?? "—" }} <span aria-hidden="true">→</span> {{ rank?.to ?? "—" }}
          </span>
        </div>
        <strong>{{ rankHours }}</strong>
        <progress
          v-if="rank?.targetHours !== null && rank?.targetHours !== undefined"
          :value="rank.pct"
          max="100"
          :aria-label="`${rank.from} progress: ${rank.pct}%`"
        >
          {{ rank.pct }}%
        </progress>
        <p>{{ promotion }}</p>
      </div>
    </div>

    <dl class="metrics">
      <div v-for="metric in metrics" :key="metric.label" class="metric">
        <dt>{{ metric.label }}</dt>
        <dd>{{ metric.value }}</dd>
      </div>
    </dl>
  </section>
</template>

<style scoped>
@layer components {
  .pv-dashboard-pilot {
    overflow: hidden;
    margin-bottom: 16px;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-xl);
    background: var(--pv-panel);
    color: var(--pv-ink);
  }
  .summary {
    display: grid;
    gap: 20px;
    padding: 18px;
  }
  .identity {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 14px;
  }
  .avatar {
    width: 56px;
    height: 56px;
    flex: 0 0 56px;
    border-radius: var(--pv-radius-lg);
    object-fit: cover;
  }
  .fallback {
    display: grid;
    place-items: center;
    background: var(--pv-accent);
    color: var(--pv-panel);
    font-size: 18px;
    font-weight: 700;
  }
  .identity-copy {
    min-width: 0;
  }
  .identity-title {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .identity-title h1 {
    margin: 0;
    font-size: 20px;
    line-height: 1.1;
  }
  .status {
    border-radius: var(--pv-radius-full);
    background: var(--pv-accent-soft);
    color: var(--pv-accent);
    font-size: 11px;
    font-weight: 650;
    padding: 3px 8px;
  }
  .status[data-c="success"] {
    color: var(--pv-green);
    background: color-mix(in srgb, var(--pv-green) 12%, transparent);
  }
  .status[data-c="warning"] {
    color: var(--pv-amber);
    background: color-mix(in srgb, var(--pv-amber) 12%, transparent);
  }
  .status[data-c="danger"] {
    color: var(--pv-red);
    background: color-mix(in srgb, var(--pv-red) 12%, transparent);
  }
  .status[data-c="gray"] {
    color: var(--pv-ink-dim);
    background: color-mix(in srgb, var(--pv-ink-dim) 12%, transparent);
  }
  .identity-copy p {
    display: flex;
    flex-wrap: wrap;
    margin: 7px 0 0;
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 12px;
    gap: 3px;
  }
  .rank {
    display: grid;
    min-width: 0;
    gap: 7px;
  }
  .rank-heading {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: var(--pv-ink-dim);
    font-size: 12px;
  }
  .rank strong {
    font-family: var(--pv-font-mono);
    font-size: 13px;
  }
  .rank progress {
    width: 100%;
    height: 8px;
    accent-color: var(--pv-accent);
  }
  .rank p {
    margin: 0;
    color: var(--pv-ink-dim);
    font-size: 11px;
  }
  .metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin: 0;
    border-top: 1px solid var(--pv-line);
  }
  .metric {
    min-width: 0;
    padding: 14px;
  }
  .metric:nth-child(odd) {
    border-right: 1px solid var(--pv-line);
  }
  .metric:last-child {
    border-right: 0;
  }
  .metric:nth-child(n + 3) {
    border-top: 1px solid var(--pv-line);
  }
  .metric dt {
    color: var(--pv-ink-faint);
    font-size: 10px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
  }
  .metric dd {
    overflow-wrap: anywhere;
    margin: 5px 0 0;
    font-size: 17px;
    font-variant-numeric: tabular-nums;
    font-weight: 650;
  }
  @media (min-width: 640px) {
    .summary {
      grid-template-columns: minmax(0, 300px) minmax(0, 1fr);
      align-items: center;
    }
    .rank {
      border-left: 1px solid var(--pv-line);
      padding-left: 20px;
    }
    .metrics {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .metric:nth-child(odd) {
      border-right: 0;
    }
    .metric:not(:nth-child(4n)) {
      border-right: 1px solid var(--pv-line);
    }
    .metric:last-child {
      border-right: 0;
    }
    .metric:nth-child(n + 3) {
      border-top: 0;
    }
    .metric:nth-child(n + 5) {
      border-top: 1px solid var(--pv-line);
    }
  }
  @media (min-width: 1200px) {
    .metrics {
      grid-template-columns: repeat(7, minmax(0, 1fr));
    }
    .metrics .metric {
      border-top: 0;
      border-right: 0;
    }
    .metric:not(:last-child) {
      border-right: 1px solid var(--pv-line);
    }
  }
}
</style>
