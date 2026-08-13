<script setup lang="ts">
import { computed, ref } from "vue";
import PvIcon from "@/shared/components/PvIcon.vue";

/**
 * VA-wide activity feed — a vertical timeline of the whole virtual airline's
 * recent activity (PIREPs filed, pilots joined, flights added, awards earned),
 * with a live "pilots flying now" count in the header. Fetches GET /api/activity
 * once on mount (the copy + ordering are built server-side, so this stays a dumb
 * timeline presenter). Bundled first-party dashboard widget (span 2).
 */
type Feed = App.Http.Data.ActivityFeedData;
type ActivityEvent = App.Http.Data.ActivityEventData;

const state = ref<{ status: "loading" | "success" | "error"; data?: Feed }>({ status: "loading" });

const flyingNow = computed(() => state.value.data?.flyingNow ?? 0);
const events = computed<ActivityEvent[]>(() => state.value.data?.events ?? []);

async function load() {
  try {
    const res = await fetch("/api/activity", {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    state.value = { status: "success", data: (await res.json()) as Feed };
  } catch {
    state.value = { status: "error" };
  }
}
load();

/** Timeline dot color, keyed by event type. */
const DOT: Record<string, string> = {
  pirep: "var(--pv-accent, #067ec1)",
  pilot_joined: "var(--pv-green, #16a34a)",
  flight_added: "var(--pv-cyan, #0e86a0)",
  award: "var(--pv-amber, #d97706)",
};
const dotColor = (type: string): string => DOT[type] ?? "var(--pv-ink-faint, #9aa1b2)";

/** Compact relative time from an ISO-8601 string ("just now", "3h ago"). */
function timeAgo(iso: string): string {
  const s = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
  if (s < 60) return "just now";
  const m = Math.floor(s / 60);
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  const d = Math.floor(h / 24);
  if (d < 30) return `${d}d ago`;
  const mo = Math.floor(d / 30);
  if (mo < 12) return `${mo}mo ago`;
  return `${Math.floor(mo / 12)}y ago`;
}
</script>

<template>
  <div class="feed">
    <header class="head">
      <span class="pulse" aria-hidden="true"><span class="pdot" /></span>
      <span class="count tnum">{{ flyingNow }}</span>
      <span class="lbl">{{ $tChoice("ui.flying_now", flyingNow) }}</span>
    </header>

    <p v-if="state.status === 'loading'" class="muted">{{ $t("ui.loading_activity") }}</p>
    <p v-else-if="state.status === 'error'" class="err">{{ $t("ui.activity_error") }}</p>
    <ol v-else-if="events.length" class="timeline">
      <li v-for="e in events" :key="e.id" class="row">
        <span class="rail">
          <span class="node" :style="{ color: dotColor(e.type) }">
            <PvIcon :name="e.icon" :size="13" />
          </span>
        </span>
        <div class="body">
          <p class="title">{{ e.title }}</p>
          <p v-if="e.subtitle" class="sub tnum">{{ e.subtitle }}</p>
        </div>
        <time class="when">{{ timeAgo(e.timestamp) }}</time>
      </li>
    </ol>
    <p v-else class="muted">{{ $t("ui.no_activity") }}</p>
  </div>
</template>

<style scoped>
.feed {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* Header — live "flying now" */
.head {
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.pulse {
  align-self: center;
  position: relative;
  width: 8px;
  height: 8px;
}
.pdot {
  position: absolute;
  inset: 0;
  border-radius: var(--pv-radius-full);
  background: var(--pv-green);
  box-shadow: 0 0 0 0 color-mix(in srgb, var(--pv-green) 60%, transparent);
  animation: pv-pulse 2s ease-out infinite;
}
@keyframes pv-pulse {
  0% {
    box-shadow: 0 0 0 0 color-mix(in srgb, var(--pv-green) 55%, transparent);
  }
  70% {
    box-shadow: 0 0 0 7px transparent;
  }
  100% {
    box-shadow: 0 0 0 0 transparent;
  }
}
.count {
  font-size: 18px;
  font-weight: 700;
  color: var(--pv-ink);
  letter-spacing: -0.01em;
}
.lbl {
  font-size: 12px;
  color: var(--pv-ink-dim);
}

/* Timeline */
.timeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}
.row {
  display: grid;
  grid-template-columns: 24px 1fr auto;
  gap: 10px;
  padding-bottom: 14px;
}
.row:last-child {
  padding-bottom: 0;
}

/* Rail + node — the vertical connecting line runs through the rail column */
.rail {
  position: relative;
  display: flex;
  justify-content: center;
}
.rail::before {
  content: "";
  position: absolute;
  top: 22px;
  bottom: -14px;
  left: 50%;
  width: 1px;
  transform: translateX(-50%);
  background: var(--pv-line);
}
.row:last-child .rail::before {
  display: none;
}
.node {
  position: relative;
  z-index: 1;
  width: 22px;
  height: 22px;
  border-radius: var(--pv-radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  color: currentColor;
  background: color-mix(in srgb, currentColor 12%, var(--pv-panel));
  border: 1px solid color-mix(in srgb, currentColor 30%, var(--pv-line));
}

.body {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.title {
  margin: 0;
  font-size: 13px;
  color: var(--pv-ink);
  line-height: 1.35;
}
.sub {
  margin: 0;
  font-family: var(--pv-font-mono);
  font-size: 11px;
  color: var(--pv-ink-dim);
}
.when {
  font-size: 11px;
  color: var(--pv-ink-faint);
  white-space: nowrap;
  padding-top: 3px;
}

.muted {
  margin: 0;
  font-size: 12px;
  color: var(--pv-ink-dim);
}
.err {
  margin: 0;
  font-size: 12px;
  color: var(--pv-red);
}
</style>
