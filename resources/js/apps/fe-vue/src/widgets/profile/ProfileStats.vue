<script setup lang="ts">
import StatTile from "@/shared/components/stats/StatTile.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";

defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <UPageCard class="pv-profile-stats" variant="outline" title="Record" aria-label="Statistics">
    <div class="stat-grid">
      <StatTile label="Total Hours" :value="profile.flightTimeMinutes" mono accent="cyan" />
      <StatTile label="Flights" :value="profile.flights" accent="green" />
      <StatTile label="Home" :value="profile.homeAirport?.icao ?? '—'" mono small accent="mag" />
      <StatTile
        label="Current"
        :value="profile.currentAirport?.icao ?? '—'"
        mono
        small
        accent="amber"
      />
    </div>
  </UPageCard>
</template>

<style scoped>
@layer components {
  .stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
  }
}
</style>
