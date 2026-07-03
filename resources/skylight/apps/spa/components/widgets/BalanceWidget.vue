<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import WsKpi from './WsKpi.vue'

const page = usePage()
const value = computed(() => (page.props.balance as { formatted: string } | null)?.formatted ?? '—')
</script>

<template>
  <WsKpi :value="value" sub="Account balance" />
</template>
