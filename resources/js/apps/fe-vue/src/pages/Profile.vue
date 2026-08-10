<script setup lang="ts">
import ProfileAwards from "@/widgets/profile/ProfileAwards.vue";
import ProfileFields from "@/widgets/profile/ProfileFields.vue";
import ProfileIdentity from "@/widgets/profile/ProfileIdentity.vue";
import ProfileStats from "@/widgets/profile/ProfileStats.vue";
import ProfileTypeRatings from "@/widgets/profile/ProfileTypeRatings.vue";

defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <div class="pv-profile">
    <ProfileIdentity :profile />
    <ProfileStats :profile />
    <ProfileTypeRatings :profile />
    <ProfileAwards :profile />
    <ProfileFields :profile />
  </div>
</template>
