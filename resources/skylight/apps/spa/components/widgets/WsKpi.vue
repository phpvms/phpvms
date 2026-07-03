<script setup lang="ts">
/** Big KPI readout used inside KPI widgets: value + optional unit + sub-line. */
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
  color: var(--pv-ink);
  letter-spacing: -0.01em;
  line-height: 1.1;
}
.unit {
  font-size: 13px;
  font-weight: 400;
  color: var(--pv-ink-faint);
  margin-left: 2px;
}
.sub {
  font-size: 12px;
  color: var(--pv-ink-dim);
}
</style>
