<script setup lang="ts">
/**
 * Big KPI readout: value + optional unit + sub-line. Vendored into this addon
 * (an ESM widget can't import skylight-internal components); mirrors the core
 * WsKpi presentation. Imports only `vue`.
 */
withDefaults(defineProps<{ value: string | number; unit?: string; sub?: string }>(), {
  unit: '',
  sub: '',
})
</script>

<template>
  <div class="kpi">
    <div class="val tnum">{{ value }}<span v-if="unit" class="unit">{{ unit }}</span></div>
    <div v-if="sub" class="sub">{{ sub }}</div>
  </div>
</template>

<style scoped>
.kpi {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.val {
  font-size: 24px;
  font-weight: 600;
  color: var(--pv-ink, #22262f);
  letter-spacing: -0.01em;
  line-height: 1.1;
}
.unit {
  font-size: 13px;
  font-weight: 400;
  color: var(--pv-ink-faint, #9aa1b2);
  margin-left: 2px;
}
.sub {
  font-size: 12px;
  color: var(--pv-ink-dim, #6b7280);
}
</style>
