<script setup lang="ts">
import PilotAwards from "@/widgets/shared/PilotAwards.vue";

defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <PilotAwards class="pv-profile-awards" :awards="profile.awards" />
</template>
