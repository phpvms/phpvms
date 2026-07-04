<script setup lang="ts">
import { computed, ref } from "vue";
import PvApp from "@/components/pv/PvApp.vue";
import StatTile from "@/components/stats/StatTile.vue";

/**
 * Profile page — the pilot's record card, Glass-Cockpit styled. Reads the flat
 * ProfilePresenter DTO only. Persistent PvApp layout (nav + header chrome).
 */
defineOptions({ layout: PvApp });

// NOTE: the DTO is passed NESTED under a single `profile` prop (not spread as
// top-level props). @vue/compiler-sfc can't expand an ambient namespace DTO used
// as the WHOLE props shape at build time (`defineProps<App.Http.Data.ProfileData>()`
// breaks the production build), but it resolves fine as a single prop's type —
// the same pattern the other DTO pages use (e.g. Pireps/Show `{ pirep: … }`).
const props = defineProps<{ profile: App.Http.Data.ProfileData }>();

const imgFailed = ref(false);
const memberYear = computed(() =>
  props.profile.memberSince ? new Date(props.profile.memberSince).getUTCFullYear().toString() : "—",
);
const initials = computed(() =>
  props.profile.name
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);
</script>

<template>
  <!-- ── IDENTITY ────────────────────────────────────────────── -->
  <section aria-label="Pilot identity">
    <p class="pv-eyebrow">PILOT PROFILE</p>

    <div class="idcard">
      <div class="avatar">
        <img
          v-if="profile.avatar && !imgFailed"
          :src="profile.avatar"
          :alt="profile.name"
          @error="imgFailed = true"
        />
        <span v-else class="initials">{{ initials }}</span>
      </div>
      <div class="who">
        <h1 class="name">{{ profile.name }}</h1>
        <div class="meta">
          <span v-if="profile.airline" class="pill"
            >{{ profile.airline.icao }} · {{ profile.airline.name }}</span
          >
          <span v-if="profile.rank" class="pill mag">{{ profile.rank.name }}</span>
          <span class="pill">{{ profile.state.label }}</span>
          <span class="since">MEMBER SINCE {{ memberYear }}</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ── STATS ───────────────────────────────────────────────── -->
  <section aria-label="Statistics" class="mt">
    <p class="pv-eyebrow">RECORD</p>
    <div class="stat-grid">
      <StatTile label="Total Hours" :value="profile.flightTimeMinutes" mono accent="cyan" />
      <StatTile label="Flights" :value="profile.flights" accent="green" />
      <StatTile label="Home" :value="profile.homeAirport?.icao ?? '—'" mono small accent="mag" />
      <StatTile
        label="Current"
        :value="profile.currentAirport?.icao ?? '—'"
        mono
        small
        accent="amber"
      />
    </div>
  </section>

  <!-- ── TYPE RATINGS ────────────────────────────────────────── -->
  <section v-if="profile.typeRatings.length" aria-label="Type ratings" class="mt">
    <p class="pv-eyebrow">TYPE RATINGS</p>
    <div class="chips">
      <span v-for="t in profile.typeRatings" :key="t.type" class="chip">
        <b>{{ t.type }}</b> {{ t.name }}
      </span>
    </div>
  </section>

  <!-- ── AWARDS ──────────────────────────────────────────────── -->
  <section v-if="profile.awards.length" aria-label="Awards" class="mt">
    <p class="pv-eyebrow">AWARDS</p>
    <div class="awards">
      <div v-for="a in profile.awards" :key="a.name" class="award">
        <img v-if="a.image" :src="a.image" :alt="a.name" class="award-img" />
        <div class="award-body">
          <span class="award-name">{{ a.name }}</span>
          <span v-if="a.description" class="award-desc">{{ a.description }}</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ── CUSTOM FIELDS ───────────────────────────────────────── -->
  <section v-if="profile.fields.length" aria-label="Fields" class="mt">
    <p class="pv-eyebrow">DETAILS</p>
    <div class="fields">
      <div v-for="f in profile.fields" :key="f.name" class="field">
        <span class="field-k">{{ f.name }}</span>
        <span class="field-v">{{ f.value || "—" }}</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
.mt {
  margin-top: 20px;
}

.idcard {
  display: flex;
  align-items: center;
  gap: 20px;
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 20px 24px;
  box-shadow: var(--pv-shadow-panel);
}
.avatar {
  width: 72px;
  height: 72px;
  border-radius: var(--pv-radius-md);
  overflow: hidden;
  flex-shrink: 0;
  border: 1px solid var(--pv-line);
  background: var(--pv-panel-inset);
  display: flex;
  align-items: center;
  justify-content: center;
}
.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.initials {
  font-family: var(--pv-font-display);
  font-weight: 700;
  font-size: calc(24px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
}
.name {
  font-family: var(--pv-font-display);
  font-weight: 700;
  font-size: calc(26px * var(--pv-type-scale));
  color: var(--pv-ink);
  letter-spacing: -0.01em;
}
.meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 8px;
}
.pill {
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-sm);
  padding: 3px 8px;
}
.pill.mag {
  color: var(--pv-accent);
  border-color: color-mix(in srgb, var(--pv-accent) 40%, var(--pv-line));
}
.since {
  font-family: var(--pv-font-mono);
  font-size: calc(8px * var(--pv-type-scale));
  letter-spacing: 0.14em;
  color: var(--pv-ink-dim);
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 12px;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.chip {
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
  color: var(--pv-ink);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-sm);
  padding: 6px 10px;
  background: var(--pv-panel);
}
.chip b {
  color: var(--pv-cyan);
  margin-right: 6px;
}

.awards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}
.award {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 12px 14px;
  box-shadow: var(--pv-shadow-panel);
}
.award-img {
  width: 40px;
  height: 40px;
  object-fit: contain;
  flex-shrink: 0;
}
.award-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.award-name {
  font-family: var(--pv-font-display);
  font-weight: 600;
  font-size: calc(13px * var(--pv-type-scale));
  color: var(--pv-ink);
}
.award-desc {
  font-size: calc(11px * var(--pv-type-scale));
  color: var(--pv-ink-dim);
}

.fields {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field {
  display: flex;
  gap: 16px;
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  padding: 10px 14px;
  box-shadow: var(--pv-shadow-panel);
}
.field-k {
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
  min-width: 160px;
}
.field-v {
  font-family: var(--pv-font-mono);
  font-size: calc(12px * var(--pv-type-scale));
  color: var(--pv-ink);
}
</style>
