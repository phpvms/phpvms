<script setup lang="ts">
defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <section
    v-if="profile.fields.length"
    aria-label="Fields"
    class="profile-section pv-profile-fields"
  >
    <p class="pv-eyebrow">DETAILS</p>
    <div class="fields">
      <div v-for="field in profile.fields" :key="field.name" class="field">
        <span class="field-k">{{ field.name }}</span>
        <span class="field-v">{{ field.value || "—" }}</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .profile-section {
    margin-top: 20px;
  }
  .fields {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .field {
    display: flex;
    gap: 16px;
    background: var(--pv-panel);
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-md);
    padding: 10px 14px;
  }
  .field-k {
    font-size: 12px;
    font-weight: 500;
    color: var(--pv-ink-dim);
    min-width: 160px;
  }
  .field-v {
    font-size: 13px;
    color: var(--pv-ink);
    font-variant-numeric: tabular-nums;
  }
}
</style>
