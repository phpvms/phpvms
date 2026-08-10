<script setup lang="ts">
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import type { HeaderUser } from "./headerTypes";

const props = defineProps<{ initials: string; user: HeaderUser | null }>();
const menuItems = computed(() => [
  [
    { label: props.user?.name ?? "Pilot", type: "label" as const },
    {
      label: [props.user?.ident, props.user?.callsign].filter(Boolean).join(" · "),
      type: "label" as const,
    },
  ],
  [
    { label: "Profile", icon: "i-lucide-user", onSelect: () => router.visit("/profile") },
    { label: "Sign out", icon: "i-lucide-log-out", onSelect: () => router.visit("/logout") },
  ],
]);
</script>

<template>
  <UButton
    v-if="!user"
    class="pv-header-sign-in"
    color="primary"
    variant="soft"
    size="sm"
    @click="router.visit('/login')"
  >
    Sign in
  </UButton>
  <UDropdownMenu v-else :items="menuItems">
    <UButton
      class="pv-header-account"
      color="neutral"
      variant="ghost"
      size="sm"
      :aria-label="`Account menu for ${user.name}`"
    >
      <img v-if="user.avatar" class="avatar" :src="user.avatar" alt="" />
      <span v-else class="avatar fallback" aria-hidden="true">{{ initials }}</span>
      <span class="identity"
        ><strong>{{ user.name }}</strong
        ><small>{{ user.callsign ?? user.ident }}</small></span
      >
    </UButton>
  </UDropdownMenu>
</template>

<style scoped>
@layer components {
  .pv-header-account {
    min-width: 0;
    color: var(--pv-ink);
  }
  .avatar {
    width: 24px;
    height: 24px;
    border-radius: var(--pv-radius-full);
    object-fit: cover;
  }
  .fallback {
    display: grid;
    place-items: center;
    background: var(--pv-accent-soft);
    color: var(--pv-accent);
    font-size: 10px;
    font-weight: 700;
  }
  .identity {
    display: grid;
    min-width: 0;
    line-height: 1.1;
    text-align: left;
  }
  .identity strong,
  .identity small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .identity strong {
    font-size: 11px;
  }
  .identity small {
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 10px;
  }
  .pv-header-account:focus-visible,
  .pv-header-sign-in:focus-visible {
    outline: 2px solid var(--pv-accent);
    outline-offset: 2px;
  }
  @media (max-width: 639px) {
    .identity {
      display: none;
    }
  }
}
</style>
