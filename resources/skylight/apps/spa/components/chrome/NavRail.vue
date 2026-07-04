<script setup lang="ts">
import { computed, ref } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import PvIcon from "@/components/pv/PvIcon.vue";

/**
 * Workspace nav rail (240px): brand, labeled destinations with icons + badges,
 * and a pilot mini-card. Active state from the live Inertia URL. SPA
 * destinations use <Link>; not-yet-ported pages use a plain <a> (full load) so
 * the Inertia client never receives a Blade response.
 */
interface Dest {
  label: string;
  href: string;
  spa: boolean;
  icon: string;
  badge?: string;
}

const dests: Dest[] = [
  { label: "Dashboard", href: "/dashboard", spa: true, icon: "layout-dashboard" },
  { label: "Flights", href: "/flights", spa: true, icon: "plane" },
  { label: "My Bids", href: "/flights/bids", spa: true, icon: "check-check" },
  { label: "Live Map", href: "/livemap", spa: false, icon: "map" },
  { label: "Logbook", href: "/pireps", spa: true, icon: "notebook-text" },
  { label: "Profile", href: "/profile", spa: true, icon: "user" },
];

interface SharedAuth {
  user: { name: string; avatar: string | null } | null;
}
const page = usePage();
const appName = computed(() => (page.props.appName as string) ?? "phpVMS");
const user = computed(() => (page.props.auth as SharedAuth | undefined)?.user ?? null);
const initials = computed(() =>
  (user.value?.name ?? "?")
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);
const imgFailed = ref(false);

// Longest-prefix-wins: on /flights/bids both '/flights' and '/flights/bids'
// prefix-match, which would mark two items active (double aria-current). Pick
// the single most-specific matching destination so exactly one item is active.
const activeHref = computed<string | null>(() => {
  const url = page.url.split("?")[0];
  let best: string | null = null;
  for (const d of dests) {
    if (url === d.href || url.startsWith(d.href + "/")) {
      if (best === null || d.href.length > best.length) best = d.href;
    }
  }
  return best;
});

function isActive(href: string) {
  return activeHref.value === href;
}
</script>

<template>
  <div class="rail scroll-thin">
    <!-- brand -->
    <div class="brand">
      <img class="logo" :src="'/assets/img/logo_blue.svg'" alt="" aria-hidden="true" />
      <div class="brandtext">
        <div class="bn">{{ appName }}</div>
        <div class="bs">Fleet Ops</div>
      </div>
    </div>

    <!-- nav -->
    <nav class="nav">
      <div class="micro sect">Workspace</div>
      <component
        :is="d.spa ? Link : 'a'"
        v-for="d in dests"
        :key="d.href"
        :href="d.href"
        class="item"
        :class="{ active: isActive(d.href) }"
        :aria-current="isActive(d.href) ? 'page' : undefined"
      >
        <PvIcon :name="d.icon" :size="17" class="i" />
        <span class="lbl">{{ d.label }}</span>
      </component>
    </nav>

    <!-- pilot mini-card -->
    <div class="mini">
      <div class="ma">
        <img
          v-if="user?.avatar && !imgFailed"
          :src="user.avatar"
          :alt="user?.name"
          @error="imgFailed = true"
        />
        <span v-else>{{ initials }}</span>
      </div>
      <div class="mt">
        <div class="mn">{{ user?.name ?? "Pilot" }}</div>
        <div class="micro ms">On duty</div>
      </div>
      <span class="dot" />
    </div>
  </div>
</template>

<style scoped>
.rail {
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}
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

.nav {
  flex: 1;
  padding: 12px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sect {
  padding: 4px 8px 6px;
}
.item {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 34px;
  padding: 0 10px;
  border-radius: var(--pv-radius-md);
  color: var(--pv-ink-dim);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
}
.item:hover {
  background: var(--pv-hover);
  color: var(--pv-ink);
}
.item.active {
  background: var(--pv-accent-soft);
  color: var(--pv-accent);
}
.item .i {
  width: 17px;
  height: 17px;
  flex-shrink: 0;
}
.lbl {
  flex: 1;
}

.mini {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-top: 1px solid var(--pv-line);
  flex-shrink: 0;
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
</style>
