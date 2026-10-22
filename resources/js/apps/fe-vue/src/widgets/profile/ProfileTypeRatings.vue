<script setup lang="ts">
import UBadge from "@nuxt/ui/components/Badge.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";

defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <UPageCard
    v-if="profile.typeRatings.length"
    class="pv-profile-type-ratings"
    variant="outline"
    title="Type ratings"
    aria-label="Type ratings"
  >
    <div class="ratings">
      <UBadge
        v-for="rating in profile.typeRatings"
        :key="rating.type"
        color="neutral"
        variant="subtle"
        size="lg"
      >
        <span class="rating-type">{{ rating.type }}</span>
        {{ rating.name }}
      </UBadge>
    </div>
  </UPageCard>
</template>

<style scoped>
@layer components {
  .ratings {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  /* Data font for the ICAO type code, per THEMING.md's mono-for-data rule. */
  .rating-type {
    font-family: var(--pv-font-mono);
    color: var(--pv-cyan);
    margin-right: 6px;
  }
}
</style>
