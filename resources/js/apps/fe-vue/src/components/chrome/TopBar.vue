<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import ZuluClock from "./ZuluClock.vue";
import { useTheme } from "@/composables/useTheme";

/**
 * Workspace top bar: breadcrumb, command-search (⌘K, visual for now), Zulu
 * clock, theme toggle, notifications, and the pilot avatar. Fills PvLayout's
 * `header` region.
 */
interface SharedAuth {
  user: { name: string; avatar: string | null } | null;
}
const page = usePage();
const user = computed(() => (page.props.auth as SharedAuth | undefined)?.user ?? null);
const station = computed(() => {
  const a = page.props.currentAirport as string | { icao?: string } | null | undefined;
  return !a ? null : typeof a === "string" ? a : (a.icao ?? null);
});
const initials = computed(() =>
  (user.value?.name ?? "?")
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);

const { isDark, toggleDark } = useTheme();
</script>

<template>
  <!-- command / search -->
  <button class="search" type="button" :aria-label="$t('ui.search')">
    <svg viewBox="0 0 24 24" class="i" fill="none" stroke="currentColor" stroke-width="1.8">
      <circle cx="11" cy="11" r="7" />
      <path d="m21 21-4.3-4.3" />
    </svg>
    <span class="ph">{{ $t("ui.search_placeholder") }}</span>
    <span class="kbd"><span>⌘</span><span>K</span></span>
  </button>

  <div class="right">
    <ZuluClock :station="station" />

    <button class="icobtn" type="button" :aria-label="$t('ui.toggle_theme')" @click="toggleDark()">
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
        <img v-if="user?.avatar" :src="user.avatar" :alt="user?.name" />
        <span v-else>{{ initials }}</span>
      </div>
      <span class="mn">{{ user?.name ?? $t("ui.role_pilot") }}</span>
    </div>
  </div>
</template>

<style scoped>
.search {
  flex: 1;
  max-width: 520px;
  height: 36px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 12px;
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-lg);
  background: var(--pv-panel-inset);
  color: var(--pv-ink-faint);
  cursor: pointer;
  font-size: 13px;
}
.search:hover {
  border-color: var(--pv-line-strong);
}
.search .i {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}
.ph {
  flex: 1;
  text-align: left;
}
.kbd {
  display: flex;
  gap: 3px;
}
.kbd span {
  font-family: var(--pv-font-mono);
  font-size: 11px;
  line-height: 1;
  padding: 2px 5px;
  border: 1px solid var(--pv-line);
  border-radius: 4px;
  color: var(--pv-ink-dim);
}

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
</style>
