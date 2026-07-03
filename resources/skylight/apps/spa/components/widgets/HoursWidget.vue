<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import WsKpi from './WsKpi.vue'

const page = usePage()
const value = computed(() => (page.props.flightTimeMinutes as string) ?? '—')
</script>

<template>
  <WsKpi :value="value" sub="Total flight time" />
</template>
