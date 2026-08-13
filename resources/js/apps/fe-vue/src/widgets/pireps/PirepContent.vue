<script setup lang="ts">
import { formatPirepDate } from "./formatPirepDate";

defineProps<{ pirep: App.Http.Data.PirepData }>();
</script>

<template>
  <main class="main pv-pirep-content">
    <div v-if="pirep.notes" class="panel">
      <p class="pv-eyebrow">NOTES</p>
      <p class="notes">{{ pirep.notes }}</p>
    </div>

    <div v-if="pirep.fields.length" class="panel">
      <p class="pv-eyebrow">CUSTOM FIELDS</p>
      <dl class="kv">
        <template v-for="field in pirep.fields" :key="field.name">
          <dt>{{ field.name }}</dt>
          <dd>{{ field.value || "—" }}</dd>
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
          <tr v-for="fare in pirep.fares" :key="fare.name + fare.code">
            <td>{{ fare.name }}</td>
            <td class="mono">{{ fare.code ?? "—" }}</td>
            <td class="num">{{ fare.count }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pirep.logs.length" class="panel">
      <p class="pv-eyebrow">ACARS LOG</p>
      <ul class="log">
        <li v-for="(entry, index) in pirep.logs" :key="index">
          <span class="lt">{{ formatPirepDate(entry.time) }}</span>
          <span class="lm">{{ entry.message }}</span>
        </li>
      </ul>
    </div>
  </main>
</template>

<style scoped>
@layer components {
  .main {
    display: grid;
    gap: 12px;
  }
  .panel {
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-md);
    background: var(--pv-panel);
    padding: 12px 14px;
  }
  .notes {
    font-size: 13px;
    color: var(--pv-ink);
    white-space: pre-wrap;
    margin: 6px 0 0;
  }
  .kv {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 6px 14px;
    margin: 8px 0 0;
    font-size: 12px;
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
    font-size: 12px;
  }
  .tbl th {
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    color: var(--pv-ink-dim);
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
    font-size: 11px;
  }
  .lt {
    color: var(--pv-ink-faint);
    white-space: nowrap;
  }
  .lm {
    color: var(--pv-ink-dim);
  }
}
</style>
