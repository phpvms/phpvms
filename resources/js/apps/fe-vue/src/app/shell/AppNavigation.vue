<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import NavigationBrand from "./NavigationBrand.vue";
import NavigationLinks from "./NavigationLinks.vue";
import PilotStatus from "./PilotStatus.vue";
import { activeMenuPath, type NavigationMenuItem } from "./navigation";
import { useAppChrome } from "./useAppChrome";

const page = usePage<{ navigation?: NavigationMenuItem[] }>();
const { appName, initials, user } = useAppChrome();
const items = computed<NavigationMenuItem[]>(() => page.props.navigation ?? []);
const activePath = computed<string | null>(() =>
  activeMenuPath(items.value, page.url.split("?")[0].replace(/\/$/, "") || "/"),
);
</script>

<template>
  <div class="rail pv-navigation scroll-thin">
    <NavigationBrand :app-name="appName" />
    <NavigationLinks :active-path="activePath" :items="items" />
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
