<script setup lang="ts">
import FlashToasts from "./FlashToasts.vue";
import { provideExtensionContext } from "./extensions/provideExtensionContext";
import AppHeader from "./shell/AppHeader.vue";
import AppNavigation from "./shell/AppNavigation.vue";
import AppShell from "./shell/AppShell.vue";
import { useThemeContext } from "@/shared/theme";

provideExtensionContext();
const { resolved } = useThemeContext();
</script>

<template>
  <UApp>
    <UTheme :props="resolved.props" :ui="resolved.ui">
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
    </UTheme>
  </UApp>
</template>
