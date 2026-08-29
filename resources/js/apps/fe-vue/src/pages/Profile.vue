<script setup lang="ts">
import { computed, shallowRef } from "vue";
import ProfileAwards from "@/widgets/profile/ProfileAwards.vue";
import ProfileFields from "@/widgets/profile/ProfileFields.vue";
import ProfileStats from "@/widgets/profile/ProfileStats.vue";
import ProfileTours from "@/widgets/profile/ProfileTours.vue";
import ProfileTypeRatings from "@/widgets/profile/ProfileTypeRatings.vue";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

/**
 * Shared pilot profile: reached at /profile/{id} for any pilot, or /profile
 * (self) for the signed-in pilot. `profile.isOwnProfile` is server-decided
 * (ProfileController::show) -- it, not client state, gates the Edit and
 * ACARS Config actions the same way the Blade theme does.
 */
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
  <UPage class="pv-profile" :aria-label="`${profile.name}, pilot profile`">
    <UPageHeader class="profile-header" headline="Pilot profile">
      <template #title>
        <div class="identity-title">
          <span class="avatar">
            <img
              v-if="profile.avatar && !imageFailed"
              :src="profile.avatar"
              :alt="profile.name"
              @error="imageFailed = true"
            />
            <span v-else class="initials" aria-hidden="true">{{ initials }}</span>
          </span>
          {{ profile.name }}
        </div>
      </template>

      <template #description>
        <div class="identity-meta">
          <UBadge v-if="profile.airline" color="neutral" variant="subtle">
            {{ profile.airline.icao }} · {{ profile.airline.name }}
          </UBadge>
          <UBadge v-if="profile.rank" color="primary" variant="subtle">{{
            profile.rank.name
          }}</UBadge>
          <UBadge color="neutral" variant="subtle">{{ profile.state.label }}</UBadge>
          <span class="since">Member since {{ memberYear }}</span>
        </div>
      </template>

      <template v-if="profile.isOwnProfile" #links>
        <UButton
          v-if="profile.acars"
          href="/profile/acars"
          color="neutral"
          variant="outline"
          size="sm"
        >
          ACARS config
        </UButton>
        <UButton :to="`/profile/${profile.id}/edit`" color="neutral" variant="outline" size="sm">
          Edit profile
        </UButton>
      </template>
    </UPageHeader>

    <UPageBody>
      <ProfileStats :profile />
      <ProfileTypeRatings :profile />
      <ProfileAwards :profile />
      <ProfileTours :profile />
      <ProfileFields :profile />
    </UPageBody>
  </UPage>
</template>

<style scoped>
@layer components {
  .pv-profile {
    min-width: 0;
  }
  .identity-title {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .avatar {
    display: flex;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-lg);
    background: color-mix(in srgb, var(--pv-accent) 14%, var(--pv-panel));
  }
  .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .initials {
    font-size: 14px;
    font-weight: 700;
    color: var(--pv-accent);
  }
  .identity-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }
  .since {
    font-size: 12px;
    color: var(--pv-ink-dim);
  }
}
</style>
