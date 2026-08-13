<script setup lang="ts">
import ZuluClock from "./ZuluClock.vue";
import type { AppUser } from "./useAppChrome";

defineProps<{
  initials: string;
  isDark: boolean;
  station: string | null;
  user: AppUser | null;
}>();

const emit = defineEmits<{ toggleTheme: [] }>();
</script>

<template>
  <div class="right pv-header-status">
    <ZuluClock class="top-clock" :station="station" />

    <button
      class="icobtn"
      type="button"
      :aria-label="$t('ui.toggle_theme')"
      @click="emit('toggleTheme')"
    >
      <svg
        v-if="!isDark"
        viewBox="0 0 24 24"
        class="i"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
      >
        <circle cx="12" cy="12" r="4" />
        <path
          d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
        />
      </svg>
      <svg
        v-else
        viewBox="0 0 24 24"
        class="i"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
      >
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
      </svg>
    </button>

    <button class="icobtn notif" type="button" :aria-label="$t('ui.notifications')">
      <svg viewBox="0 0 24 24" class="i" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0" />
      </svg>
      <span class="badge" />
    </button>

    <div class="sep" />

    <div class="me">
      <div class="ava">
        <img v-if="user?.avatar" :src="user.avatar" :alt="user.name" />
        <span v-else>{{ initials }}</span>
      </div>
      <span class="mn">{{ user?.name ?? $t("ui.role_pilot") }}</span>
    </div>
  </div>
</template>

<style scoped>
@layer components {
  .right {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .icobtn {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: var(--pv-radius-md);
    color: var(--pv-ink-dim);
    position: relative;
    cursor: pointer;
  }
  .icobtn:hover {
    background: var(--pv-hover);
    color: var(--pv-ink);
  }
  .icobtn .i {
    width: 18px;
    height: 18px;
  }
  .notif .badge {
    position: absolute;
    top: 7px;
    right: 7px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--pv-accent);
    border: 2px solid var(--pv-panel);
  }
  .sep {
    width: 1px;
    height: 22px;
    background: var(--pv-line);
    margin: 0 2px;
  }
  .me {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-right: 2px;
  }
  .ava {
    width: 30px;
    height: 30px;
    border-radius: var(--pv-radius-full);
    overflow: hidden;
    background: color-mix(in srgb, var(--pv-accent) 14%, var(--pv-panel));
    color: var(--pv-accent);
    display: grid;
    place-items: center;
    font-size: 11px;
    font-weight: 600;
  }
  .ava img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .mn {
    font-size: 12px;
    font-weight: 500;
    color: var(--pv-ink);
  }
  @media (max-width: 900px) {
    .mn {
      display: none;
    }
  }
  @media (max-width: 640px) {
    .top-clock,
    .sep {
      display: none;
    }
    .right {
      margin-left: auto;
      gap: 4px;
    }
    .icobtn {
      width: 44px;
      height: 44px;
    }
  }
}
</style>
