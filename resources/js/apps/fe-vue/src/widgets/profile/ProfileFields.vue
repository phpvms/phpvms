<script setup lang="ts">
import UPageCard from "@nuxt/ui/components/PageCard.vue";

defineProps<{ profile: App.Http.Data.ProfileData }>();
</script>

<template>
  <UPageCard
    v-if="profile.fields.length"
    class="pv-profile-fields"
    variant="outline"
    title="Details"
    aria-label="Fields"
  >
    <dl class="fields">
      <div v-for="field in profile.fields" :key="field.name" class="field">
        <dt class="field-k">{{ field.name }}</dt>
        <dd class="field-v">{{ field.value || "—" }}</dd>
      </div>
    </dl>
  </UPageCard>
</template>

<style scoped>
@layer components {
  .fields {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
  }
  .field {
    display: flex;
    gap: 16px;
  }
  .field-k {
    font-size: 12px;
    font-weight: 500;
    color: var(--pv-ink-dim);
    min-width: 160px;
  }
  .field-v {
    margin: 0;
    font-size: 13px;
    color: var(--pv-ink);
    font-variant-numeric: tabular-nums;
  }
}
</style>
