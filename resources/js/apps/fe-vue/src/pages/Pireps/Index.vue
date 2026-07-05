<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";

/**
 * Logbook — the pilot's PIREP list. Card-per-PIREP, modeled on the admin
 * PirepResource row card (ident + state badge, route, stat grid). Rows are
 * PirepListItemData, GENERATED from the PHP DTO (App.Http.Data.* ambient global).
 * Each card links to the SPA detail page.
 */

const props = defineProps<{
  pireps: App.Http.Data.PirepListItemData[];
  pagination: { currentPage: number; lastPage: number; total: number };
}>();

function fmtDate(iso: string | null): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString(undefined, {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function goto(page: number): void {
  if (page < 1 || page > props.pagination.lastPage) return;
  router.get("/pireps", { page }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
  <section aria-label="Logbook">
    <div class="head">
      <p class="pv-eyebrow">LOGBOOK · MY PIREPS</p>
      <span class="count">{{ pagination.total }} total</span>
    </div>

    <div v-if="pireps.length" class="cards">
      <Link v-for="p in pireps" :key="p.id" :href="`/pireps/${p.id}`" class="card">
        <div class="r1">
          <span class="ident">{{ p.ident }}</span>
          <span class="badge" :data-c="p.stateColor">{{ p.state }}</span>
        </div>

        <div class="route">
          <div class="ap">
            <span class="icao">{{ p.dpt }}</span>
            <span class="apname">{{ p.dptName ?? "" }}</span>
          </div>
          <span class="arrow" aria-hidden="true">→</span>
          <div class="ap ar">
            <span class="icao">{{ p.arr }}</span>
            <span class="apname">{{ p.arrName ?? "" }}</span>
          </div>
        </div>

        <div class="stats">
          <div class="stat">
            <span class="k">Aircraft</span><span class="v">{{ p.aircraft ?? "—" }}</span>
          </div>
          <div class="stat">
            <span class="k">Time</span><span class="v">{{ p.flightTime ?? "—" }}</span>
          </div>
          <div class="stat">
            <span class="k">Distance</span><span class="v">{{ p.distance ?? "—" }}</span>
          </div>
          <div class="stat">
            <span class="k">Score</span><span class="v">{{ p.score ?? "—" }}</span>
          </div>
          <div class="stat">
            <span class="k">Filed</span><span class="v">{{ fmtDate(p.submittedAt) }}</span>
          </div>
        </div>
      </Link>
    </div>

    <div v-else class="empty">No PIREPs yet — fly a flight to fill your logbook</div>

    <div v-if="pagination.lastPage > 1" class="pager">
      <button :disabled="pagination.currentPage <= 1" @click="goto(pagination.currentPage - 1)">
        ← Prev
      </button>
      <span class="pg">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span>
      <button
        :disabled="pagination.currentPage >= pagination.lastPage"
        @click="goto(pagination.currentPage + 1)"
      >
        Next →
      </button>
    </div>
  </section>
</template>

<style scoped>
.head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}
.count {
  font-size: 12px;
  color: var(--pv-ink-faint);
  font-variant-numeric: tabular-nums;
}

.cards {
  margin-top: 10px;
  display: grid;
  gap: 8px;
}
.card {
  display: block;
  text-decoration: none;
  color: var(--pv-ink);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel);
  padding: 12px 14px;
}
.card:hover {
  background: var(--pv-hover);
  border-color: color-mix(in srgb, var(--pv-accent) 40%, var(--pv-line));
}

.r1 {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.ident {
  font-family: var(--pv-font-mono);
  font-weight: 600;
  color: var(--pv-accent);
  font-size: 13px;
}

.route {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}
.ap {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.ap.ar {
  text-align: right;
}
.icao {
  font-family: var(--pv-font-mono);
  font-weight: 500;
  font-size: 13px;
}
.apname {
  font-size: 11px;
  color: var(--pv-ink-dim);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.arrow {
  color: var(--pv-ink-faint);
  flex-shrink: 0;
}

.stats {
  display: flex;
  flex-wrap: wrap;
  gap: 14px 22px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--pv-line);
}
.stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.stat .k {
  font-size: 10px;
  text-transform: uppercase;
  color: var(--pv-ink-faint);
}
.stat .v {
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}

.badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: var(--pv-radius-full);
  white-space: nowrap;
}
.badge[data-c="success"] {
  color: var(--pv-green);
  background: color-mix(in srgb, var(--pv-green) 12%, transparent);
}
.badge[data-c="warning"] {
  color: var(--pv-amber);
  background: color-mix(in srgb, var(--pv-amber) 12%, transparent);
}
.badge[data-c="danger"] {
  color: var(--pv-red);
  background: color-mix(in srgb, var(--pv-red) 12%, transparent);
}
.badge[data-c="info"] {
  color: var(--pv-accent);
  background: var(--pv-accent-soft);
}
.badge[data-c="gray"] {
  color: var(--pv-ink-dim);
  background: color-mix(in srgb, var(--pv-ink-dim) 12%, transparent);
}

.empty {
  font-size: 13px;
  color: var(--pv-ink-dim);
  border: 1px dashed var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 24px;
  text-align: center;
  margin-top: 10px;
}

.pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 14px;
}
.pager button {
  font-size: 12px;
  padding: 5px 12px;
  border-radius: var(--pv-radius-sm);
  border: 1px solid var(--pv-line);
  background: var(--pv-panel);
  color: var(--pv-ink);
  cursor: pointer;
}
.pager button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.pager button:not(:disabled):hover {
  border-color: var(--pv-accent);
  color: var(--pv-accent);
}
.pg {
  font-size: 12px;
  color: var(--pv-ink-dim);
}
</style>
