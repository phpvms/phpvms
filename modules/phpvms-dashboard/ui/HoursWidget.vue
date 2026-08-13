<script setup lang="ts">
import WsKpi from './WsKpi.vue'

/**
 * Total-hours KPI. Fed the DashboardData `flightTimeMinutes` string via a
 * `value => '@flightTimeMinutes'` ref resolved by the Dashboard before binding
 * (an ESM addon widget can't call usePage()). Imports only `vue` + the vendored
 * WsKpi.
 */
defineProps<{ value?: string | number | null }>()
</script>

<template>
  <WsKpi :value="value ?? '—'" sub="Total flight time" />
</template>
