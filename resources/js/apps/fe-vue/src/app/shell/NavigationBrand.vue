<script setup lang="ts">
defineProps<{ appName: string }>();
</script>

<template>
  <div class="brand pv-navigation-brand">
    <img class="logo" :src="'/assets/img/logo_blue.svg'" alt="" aria-hidden="true" />
    <div class="brandtext">
      <div class="bn">{{ appName }}</div>
      <div class="bs">{{ $t("ui.brand_tagline") }}</div>
    </div>
  </div>
</template>

<style scoped>
@layer components {
  .brand {
    height: var(--pv-header-height, 56px);
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 14px;
    border-bottom: 1px solid var(--pv-line);
    flex-shrink: 0;
  }
  .logo {
    width: 26px;
    height: 26px;
    object-fit: contain;
  }
  .bn {
    font-size: 13px;
    font-weight: 600;
    color: var(--pv-ink);
  }
  .bs {
    font-size: 11px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--pv-ink-faint);
  }
}
</style>
