<script setup lang="ts">
import { computed } from 'vue'
import WsKpi from './WsKpi.vue'

/**
 * Account-balance KPI. Fed the DashboardData `balance` object (BalanceData) via
 * a `balance => '@balance'` ref; renders its `formatted` string.
 */
const props = defineProps<{ balance?: { formatted: string } | null }>()
const value = computed(() => props.balance?.formatted ?? '—')
</script>

<template>
  <WsKpi :value="value" sub="Account balance" />
</template>
