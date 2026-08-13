<script setup lang="ts">
defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <section
    v-if="profile.typeRatings.length"
    aria-label="Type ratings"
    class="profile-section pv-profile-type-ratings"
  >
    <p class="pv-eyebrow">TYPE RATINGS</p>
    <div class="chips">
      <span v-for="rating in profile.typeRatings" :key="rating.type" class="chip">
        <b>{{ rating.type }}</b> {{ rating.name }}
      </span>
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .profile-section {
    margin-top: 20px;
  }
  .chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .chip {
    font-size: 12px;
    color: var(--pv-ink);
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-md);
    padding: 6px 10px;
    background: var(--pv-panel-inset);
  }
  .chip b {
    font-family: var(--pv-font-mono);
    color: var(--pv-cyan);
    margin-right: 6px;
  }
}
</style>
