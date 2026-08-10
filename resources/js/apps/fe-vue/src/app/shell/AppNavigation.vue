<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import NavigationBrand from "./NavigationBrand.vue";
import NavigationLinks from "./NavigationLinks.vue";
import PilotStatus from "./PilotStatus.vue";
import { navigationDestinations } from "./navigation";
import { useAppChrome } from "./useAppChrome";

const page = usePage();
const { appName, initials, user } = useAppChrome();
const activeHref = computed<string | null>(() => {
  const url = page.url.split("?")[0];
  let bestMatch: string | null = null;

  for (const destination of navigationDestinations) {
    if (url === destination.href || url.startsWith(`${destination.href}/`)) {
      if (bestMatch === null || destination.href.length > bestMatch.length) {
        bestMatch = destination.href;
      }
    }
  }

  return bestMatch;
});
</script>

<template>
  <div class="rail pv-navigation scroll-thin">
    <NavigationBrand :app-name="appName" />
    <NavigationLinks :active-href="activeHref" :destinations="navigationDestinations" />
    <PilotStatus :initials :user />
  </div>
</template>

<style scoped>
@layer components {
  .rail {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }
}
</style>
