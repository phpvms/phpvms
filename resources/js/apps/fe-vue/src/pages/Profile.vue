<script setup lang="ts">
import { computed } from "vue";
import ProfileApiAccessCard from "@/components/profile/ProfileApiAccessCard.vue";
import ProfileConnectionsCard from "@/components/profile/ProfileConnectionsCard.vue";
import ProfileEditCard from "@/components/profile/ProfileEditCard.vue";
import PvSlot from "@/shared/components/PvSlot.vue";
import ProfileAwards from "@/widgets/profile/ProfileAwards.vue";
import ProfileFields from "@/widgets/profile/ProfileFields.vue";
import ProfileStats from "@/widgets/profile/ProfileStats.vue";
import ProfileTours from "@/widgets/profile/ProfileTours.vue";
import ProfileTypeRatings from "@/widgets/profile/ProfileTypeRatings.vue";
import UAvatar from "@nuxt/ui/components/Avatar.vue";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UButton from "@nuxt/ui/components/Button.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

/**
 * Shared pilot profile: reached at /profile/{id} for any pilot, or /profile
 * (self) for the signed-in pilot. `profile.isOwnProfile` is server-decided
 * (ProfileController::show) -- it, not client state, gates the ACARS Config
 * action and the edit card below.
 *
 * `profileEdit` is only sent for your own profile (it carries your email), so
 * it is optional here and the card is skipped when it is absent.
 */
const props = defineProps<{
  profile: App.Http.Data.ProfileData;
  profileEdit?: App.Http.Data.ProfileEditData | null;
}>();

const memberYear = computed(() =>
  props.profile.memberSince ? new Date(props.profile.memberSince).getUTCFullYear().toString() : "—",
);
</script>

<template>
  <UPage class="pv-profile" :aria-label="`${profile.name}, pilot profile`">
    <UPageHeader class="profile-header" headline="Pilot profile">
      <template #title>
        <div class="identity-title">
          <!-- UAvatar derives initials from `alt` and swaps to them itself when
               the image fails to load. -->
          <UAvatar :src="profile.avatar ?? undefined" :alt="profile.name" size="lg" />
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

      <template v-if="profile.isOwnProfile && profile.acars" #links>
        <UButton href="/profile/acars" color="neutral" variant="outline" size="sm">
          ACARS config
        </UButton>
      </template>
    </UPageHeader>

    <UPageBody>
      <ProfileStats :profile />
      <ProfileTypeRatings :profile />
      <ProfileAwards :profile />
      <ProfileTours :profile />
      <ProfileFields :profile />

      <!-- Addon extension point: a card-shaped outlet on the profile body. The
           context carries the profile DTO (including `isOwnProfile`) so an entry
           can decide for itself whether it belongs on someone else's profile. -->
      <PvSlot name="profile.cards" :context="{ profile }" />

      <template v-if="profile.isOwnProfile && profileEdit">
        <ProfileEditCard :edit="profileEdit" :profile-id="profile.id" />
        <ProfileConnectionsCard :connections="profileEdit.connections" />
        <ProfileApiAccessCard :api-key="profileEdit.apiKey" />
      </template>
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
