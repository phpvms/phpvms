<script setup lang="ts">
defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <section
    v-if="profile.awards.length"
    aria-label="Awards"
    class="profile-section pv-profile-awards"
  >
    <p class="pv-eyebrow">AWARDS</p>
    <div class="awards">
      <div v-for="award in profile.awards" :key="award.name" class="award">
        <img v-if="award.image" :src="award.image" :alt="award.name" class="award-img" />
        <div class="award-body">
          <span class="award-name">{{ award.name }}</span>
          <span v-if="award.description" class="award-desc">{{ award.description }}</span>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .profile-section {
    margin-top: 20px;
  }
  .awards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
  }
  .award {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--pv-panel);
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-xl);
    padding: 12px 14px;
  }
  .award-img {
    width: 40px;
    height: 40px;
    object-fit: contain;
    flex-shrink: 0;
  }
  .award-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .award-name {
    font-weight: 600;
    font-size: 13px;
    color: var(--pv-ink);
  }
  .award-desc {
    font-size: 11px;
    color: var(--pv-ink-dim);
  }
}
</style>
