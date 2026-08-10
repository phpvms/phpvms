<script setup lang="ts">
import { computed, shallowRef } from "vue";

const props = defineProps<{ profile: App.Http.Data.ProfileData }>();
const imageFailed = shallowRef(false);
const memberYear = computed(() =>
  props.profile.memberSince ? new Date(props.profile.memberSince).getUTCFullYear().toString() : "—",
);
const initials = computed(() =>
  props.profile.name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);
</script>

<template>
  <section class="pv-profile-identity" aria-label="Pilot identity">
    <p class="pv-eyebrow">PILOT PROFILE</p>
    <div class="idcard">
      <div class="avatar">
        <img
          v-if="profile.avatar && !imageFailed"
          :src="profile.avatar"
          :alt="profile.name"
          @error="imageFailed = true"
        />
        <span v-else class="initials">{{ initials }}</span>
      </div>
      <div class="who">
        <h1 class="name">{{ profile.name }}</h1>
        <div class="meta">
          <span v-if="profile.airline" class="pill">
            {{ profile.airline.icao }} · {{ profile.airline.name }}
          </span>
          <span v-if="profile.rank" class="pill mag">{{ profile.rank.name }}</span>
          <span class="pill">{{ profile.state.label }}</span>
          <span class="since">Member since {{ memberYear }}</span>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .idcard {
    display: flex;
    align-items: center;
    gap: 20px;
    background: var(--pv-panel);
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-xl);
    padding: 20px 24px;
  }
  .avatar {
    width: 72px;
    height: 72px;
    border-radius: var(--pv-radius-lg);
    overflow: hidden;
    flex-shrink: 0;
    border: 1px solid var(--pv-line);
    background: color-mix(in srgb, var(--pv-accent) 14%, var(--pv-panel));
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
    font-weight: 700;
    font-size: 20px;
    color: var(--pv-accent);
  }
  .name {
    font-size: 18px;
    font-weight: 600;
    color: var(--pv-ink);
  }
  .meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
  }
  .pill {
    font-size: 11px;
    font-weight: 500;
    color: var(--pv-ink-dim);
    background: var(--pv-panel-inset);
    border-radius: var(--pv-radius-full);
    padding: 2px 8px;
  }
  .pill.mag {
    font-weight: 600;
    color: var(--pv-accent);
    background: var(--pv-accent-soft);
  }
  .since {
    font-size: 11px;
    color: var(--pv-ink-dim);
  }
}
</style>
