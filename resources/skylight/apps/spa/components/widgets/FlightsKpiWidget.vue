<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import WsKpi from './WsKpi.vue'

const page = usePage()
const value = computed(() => (page.props.flights as number) ?? 0)
</script>

<template>
  <WsKpi :value="value" sub="Accepted flights" />
</template>
