<script setup lang="ts">
import PirepDetailView from "@/widgets/pireps/PirepDetailView.vue";

defineProps<{ pirep: App.Http.Data.PirepData }>();
</script>

<template>
  <PirepDetailView :pirep />
</template>
