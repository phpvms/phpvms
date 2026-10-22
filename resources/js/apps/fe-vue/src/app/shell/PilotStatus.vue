<script setup lang="ts">
import { computed, shallowRef } from "vue";
import { router } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import type { AppUser } from "./useAppChrome";
import { useHeaderTheme, type ThemeMode } from "./useHeaderTheme";
import IconLogout from "~icons/tabler/logout";
import IconUser from "~icons/tabler/user";
import UDropdownMenu from "@nuxt/ui/components/DropdownMenu.vue";

const props = defineProps<{ initials: string; user: AppUser | null }>();
const imageFailed = shallowRef(false);
const { mode, select } = useHeaderTheme();
const themeModes: ThemeMode[] = ["light", "dark", "auto"];

const menuItems = computed(() => {
  const groups: Array<Array<Record<string, unknown>>> = [];

  if (props.user) {
    groups.push([
      { label: props.user.name, type: "label" as const },
      {
        label: [props.user.ident, props.user.callsign].filter(Boolean).join(" · "),
        type: "label" as const,
      },
    ]);
    groups.push([
      {
        label: trans("common.profile"),
        icon: IconUser,
        onSelect: () => router.visit("/profile"),
      },
      {
        label: trans("ui.sign_out"),
        icon: IconLogout,
        onSelect: () => router.visit("/logout"),
      },
    ]);
  } else {
    groups.push([
      { label: trans("ui.sign_in"), icon: IconUser, onSelect: () => router.visit("/login") },
    ]);
  }

  groups.push([
    { label: trans("ui.theme"), type: "label" as const },
    ...themeModes.map((item) => ({
      label: trans(`ui.theme_${item}`),
      type: "checkbox" as const,
      checked: item === mode.value,
      onSelect: () => select(item),
    })),
  ]);

  return groups;
});
</script>

<template>
  <UDropdownMenu :items="menuItems">
    <button
      type="button"
      class="mini pv-pilot-status"
      :aria-label="user ? `Account menu for ${user.name}` : 'Account menu'"
    >
      <div class="ma">
        <img
          v-if="user?.avatar && !imageFailed"
          :src="user.avatar"
          :alt="user.name"
          @error="imageFailed = true"
        />
        <span v-else>{{ initials }}</span>
      </div>
      <div class="mt">
        <div class="mn">{{ user?.name ?? $t("ui.role_pilot") }}</div>
        <div class="micro ms">{{ $t("ui.on_duty") }}</div>
      </div>
      <span class="dot" />
    </button>
  </UDropdownMenu>
</template>

<style scoped>
@layer components {
  .mini {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 0;
    border-top: 1px solid var(--pv-line);
    border-radius: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
    flex-shrink: 0;
  }
  .mini:hover {
    background: var(--pv-hover);
  }
  .mini:focus-visible {
    outline: 2px solid var(--pv-accent);
    outline-offset: -2px;
  }
  .ma {
    width: 32px;
    height: 32px;
    border-radius: var(--pv-radius-full);
    overflow: hidden;
    flex-shrink: 0;
    background: color-mix(in srgb, var(--pv-accent) 14%, var(--pv-panel));
    color: var(--pv-accent);
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 600;
  }
  .ma img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .mt {
    flex: 1;
    min-width: 0;
  }
  .mn {
    font-size: 12px;
    font-weight: 500;
    color: var(--pv-ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .ms {
    color: var(--pv-green) !important;
  }
  .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--pv-green);
    flex-shrink: 0;
  }
}
</style>
