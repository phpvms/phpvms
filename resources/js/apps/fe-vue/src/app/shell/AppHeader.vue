<script setup lang="ts">
import HeaderSearch from "./HeaderSearch.vue";
import HeaderStatus from "./HeaderStatus.vue";
import { useAppChrome } from "./useAppChrome";
import { useTheme } from "@/shared/lib/useTheme";

const { initials, station, user } = useAppChrome();
const { isDark, toggleDark } = useTheme();
</script>

<template>
  <HeaderSearch />
  <HeaderStatus :initials :is-dark="isDark" :station :user @toggle-theme="toggleDark" />
</template>
