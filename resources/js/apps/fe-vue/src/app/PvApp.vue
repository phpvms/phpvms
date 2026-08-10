<script setup lang="ts">
import FlashToasts from "./FlashToasts.vue";
import { provideExtensionContext } from "./extensions/provideExtensionContext";
import AppHeader from "./shell/AppHeader.vue";
import AppNavigation from "./shell/AppNavigation.vue";
import AppShell from "./shell/AppShell.vue";

provideExtensionContext();
</script>

<template>
  <AppShell>
    <template #navigation>
      <AppNavigation />
    </template>
    <template #header>
      <AppHeader />
    </template>
    <template #main>
      <slot />
    </template>
  </AppShell>
  <FlashToasts />
</template>
