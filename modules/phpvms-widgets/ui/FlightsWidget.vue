<script setup lang="ts">
import WsKpi from './WsKpi.vue'

/**
 * Accepted-flights KPI. Fed the DashboardData `flights` count via a
 * `value => '@flights'` ref resolved by the Dashboard before binding.
 */
defineProps<{ value?: number | null }>()
</script>

<template>
  <WsKpi :value="value ?? 0" sub="Accepted flights" />
</template>
