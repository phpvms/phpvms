<script setup lang="ts">
import StatTile from "@/shared/components/stats/StatTile.vue";

defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <section aria-label="Statistics" class="profile-section pv-profile-stats">
    <p class="pv-eyebrow">RECORD</p>
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
  </section>
</template>

<style scoped>
@layer components {
  .profile-section {
    margin-top: 20px;
  }
  .stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
  }
}
</style>
