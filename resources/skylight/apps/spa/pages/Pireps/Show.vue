<script setup lang="ts">
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import PvApp from "@/components/pv/PvApp.vue";

/**
 * PIREP detail — modeled on the admin ViewPirep (hero + stat strip + main
 * sections + sidebar). `pirep` is PirepData, GENERATED from the PHP DTO. This is
 * the SPA projection; the Blade path still renders the full page (map, comments,
 * finances ledger). v1 covers header, stats, route, notes, custom fields, fares,
 * and the ACARS log.
 */
defineOptions({ layout: PvApp });

const props = defineProps<{ pirep: App.Http.Data.PirepData }>();

const stats = computed(() => [
  { k: "Flight Time", v: props.pirep.flightTime ?? "—" },
  { k: "Distance", v: props.pirep.distance ?? "—" },
  { k: "Score", v: props.pirep.score != null ? String(props.pirep.score) : "—" },
  {
    k: "Landing Rate",
    v: props.pirep.landingRate != null ? `${Math.round(props.pirep.landingRate)} fpm` : "—",
  },
  { k: "Fuel Used", v: props.pirep.fuelUsed ?? "—" },
  { k: "Cruise", v: props.pirep.cruise ?? "—" },
]);

const details = computed(() =>
  [
    { k: "Airline", v: props.pirep.airline },
    { k: "Flight Type", v: props.pirep.flightType },
    { k: "Status", v: props.pirep.status },
    {
      k: "Source",
      v: props.pirep.sourceName
        ? `${props.pirep.source} · ${props.pirep.sourceName}`
        : props.pirep.source,
    },
    { k: "Route", v: props.pirep.route },
    { k: "Planned Time", v: props.pirep.plannedFlightTime },
    { k: "Planned Distance", v: props.pirep.plannedDistance },
    { k: "Block Fuel", v: props.pirep.blockFuel },
  ].filter((d) => d.v),
);

function fmtDate(iso: string | null): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleString(undefined, {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}
</script>

<template>
  <section aria-label="PIREP detail">
    <Link href="/pireps" class="back">← Logbook</Link>

    <header class="hero">
      <div class="hl">
        <h1 class="ident">{{ pirep.ident }}</h1>
        <div class="sub">{{ pirep.aircraft ?? "—" }}</div>
        <div class="sub2">
          <template v-if="pirep.pilotName"
            >{{ pirep.pilotName }}<span v-if="pirep.pilotRank"> · {{ pirep.pilotRank }}</span> ·
          </template>
          Filed {{ fmtDate(pirep.submittedAt) }}
        </div>
      </div>
      <span class="badge big" :data-c="pirep.stateColor">{{ pirep.state }}</span>
    </header>

    <div class="route">
      <div class="ap">
        <span class="icao">{{ pirep.dpt }}</span>
        <span class="apname">{{ pirep.dptName ?? "" }}</span>
      </div>
      <span class="arrow" aria-hidden="true">→</span>
      <div class="ap ar">
        <span class="icao">{{ pirep.arr }}</span>
        <span class="apname">{{ pirep.arrName ?? "" }}</span>
      </div>
    </div>

    <div class="strip">
      <div v-for="s in stats" :key="s.k" class="cell">
        <span class="k">{{ s.k }}</span>
        <span class="v">{{ s.v }}</span>
      </div>
    </div>

    <div class="grid">
      <main class="main">
        <div v-if="pirep.notes" class="panel">
          <p class="pv-eyebrow">NOTES</p>
          <p class="notes">{{ pirep.notes }}</p>
        </div>

        <div v-if="pirep.fields.length" class="panel">
          <p class="pv-eyebrow">CUSTOM FIELDS</p>
          <dl class="kv">
            <template v-for="f in pirep.fields" :key="f.name">
              <dt>{{ f.name }}</dt>
              <dd>{{ f.value || "—" }}</dd>
            </template>
          </dl>
        </div>

        <div v-if="pirep.fares.length" class="panel">
          <p class="pv-eyebrow">FARES</p>
          <table class="tbl">
            <thead>
              <tr>
                <th>Class</th>
                <th>Code</th>
                <th class="num">Count</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in pirep.fares" :key="f.name + f.code">
                <td>{{ f.name }}</td>
                <td class="mono">{{ f.code ?? "—" }}</td>
                <td class="num">{{ f.count }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pirep.logs.length" class="panel">
          <p class="pv-eyebrow">ACARS LOG</p>
          <ul class="log">
            <li v-for="(l, i) in pirep.logs" :key="i">
              <span class="lt">{{ fmtDate(l.time) }}</span>
              <span class="lm">{{ l.message }}</span>
            </li>
          </ul>
        </div>
      </main>

      <aside class="side">
        <div class="panel">
          <p class="pv-eyebrow">FLIGHT DETAILS</p>
          <dl class="kv">
            <template v-for="d in details" :key="d.k">
              <dt>{{ d.k }}</dt>
              <dd>{{ d.v }}</dd>
            </template>
          </dl>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.back {
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
  text-decoration: none;
}
.back:hover {
  color: var(--pv-accent);
}

.hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-top: 8px;
}
.ident {
  font-family: var(--pv-font-mono);
  font-weight: 600;
  color: var(--pv-accent);
  font-size: calc(20px * var(--pv-type-scale));
  margin: 0;
}
.sub {
  font-size: calc(12px * var(--pv-type-scale));
  color: var(--pv-ink);
  margin-top: 2px;
}
.sub2 {
  font-size: calc(11px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
  margin-top: 2px;
}

.route {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 14px;
  padding: 12px 14px;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel);
}
.ap {
  display: flex;
  flex-direction: column;
}
.ap.ar {
  text-align: right;
  margin-left: auto;
}
.icao {
  font-family: var(--pv-font-mono);
  font-weight: 600;
  font-size: calc(15px * var(--pv-type-scale));
}
.apname {
  font-size: calc(10px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
}
.arrow {
  color: var(--pv-ink-faint);
}

.strip {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
  gap: 1px;
  margin-top: 12px;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  overflow: hidden;
  background: var(--pv-line);
}
.cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 10px 12px;
  background: var(--pv-panel);
}
.cell .k {
  font-family: var(--pv-font-mono);
  font-size: calc(8.5px * var(--pv-type-scale));
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--pv-ink-faint);
}
.cell .v {
  font-size: calc(13px * var(--pv-type-scale));
  font-variant-numeric: tabular-nums;
}

.grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  margin-top: 12px;
}
@media (min-width: 860px) {
  .grid {
    grid-template-columns: 2fr 1fr;
  }
}

.panel {
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  background: var(--pv-panel);
  padding: 12px 14px;
}
.main {
  display: grid;
  gap: 12px;
}
.side {
  display: grid;
  gap: 12px;
  align-content: start;
}
.notes {
  font-size: calc(12px * var(--pv-type-scale));
  color: var(--pv-ink);
  white-space: pre-wrap;
  margin: 6px 0 0;
}

.kv {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 6px 14px;
  margin: 8px 0 0;
  font-size: calc(11.5px * var(--pv-type-scale));
}
.kv dt {
  color: var(--pv-ink-faint);
  white-space: nowrap;
}
.kv dd {
  margin: 0;
  text-align: right;
  color: var(--pv-ink);
}

.tbl {
  width: 100%;
  border-collapse: collapse;
  margin-top: 8px;
  font-size: calc(11.5px * var(--pv-type-scale));
}
.tbl th {
  text-align: left;
  font-family: var(--pv-font-mono);
  font-size: calc(8.5px * var(--pv-type-scale));
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--pv-ink-faint);
  padding: 4px 6px;
  border-bottom: 1px solid var(--pv-line);
}
.tbl td {
  padding: 6px;
  border-bottom: 1px solid var(--pv-line);
}
.tbl tr:last-child td {
  border-bottom: none;
}
.mono {
  font-family: var(--pv-font-mono);
}
.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.log {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  display: grid;
  gap: 3px;
  max-height: 320px;
  overflow: auto;
}
.log li {
  display: flex;
  gap: 10px;
  font-family: var(--pv-font-mono);
  font-size: calc(10.5px * var(--pv-type-scale));
}
.lt {
  color: var(--pv-ink-faint);
  white-space: nowrap;
}
.lm {
  color: var(--pv-ink-dim);
}

.badge {
  font-family: var(--pv-font-mono);
  font-size: calc(9.5px * var(--pv-type-scale));
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: var(--pv-radius-sm, 6px);
  border: 1px solid currentColor;
  white-space: nowrap;
}
.badge.big {
  font-size: calc(11px * var(--pv-type-scale));
  padding: 5px 12px;
}
.badge[data-c="success"] {
  color: var(--pv-green, #16a34a);
  background: color-mix(in srgb, var(--pv-green, #16a34a) 12%, transparent);
}
.badge[data-c="warning"] {
  color: #d97706;
  background: color-mix(in srgb, #d97706 12%, transparent);
}
.badge[data-c="danger"] {
  color: #dc2626;
  background: color-mix(in srgb, #dc2626 12%, transparent);
}
.badge[data-c="info"] {
  color: var(--pv-accent);
  background: var(--pv-accent-soft);
}
.badge[data-c="gray"] {
  color: var(--pv-ink-dim);
  background: color-mix(in srgb, var(--pv-ink-dim) 12%, transparent);
}
</style>
