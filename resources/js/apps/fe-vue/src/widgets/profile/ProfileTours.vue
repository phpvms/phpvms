<script setup lang="ts">
import IconRoute from "~icons/tabler/route";
import UAvatar from "@nuxt/ui/components/Avatar.vue";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UPageCard from "@nuxt/ui/components/PageCard.vue";

defineProps<{ profile: App.Http.Data.ProfileData }>();

function formatCompletedAt(value: string | null): string | null {
  return value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(new Date(value))
    : null;
}
</script>

<template>
  <UPageCard
    class="pv-profile-tours"
    variant="outline"
    title="Tours completed"
    aria-label="Tours completed"
  >
    <template #description>
      <UBadge color="neutral" variant="subtle">{{ profile.tours.length }} completed</UBadge>
    </template>

    <p v-if="!profile.tours.length" class="empty">No tours completed yet.</p>

    <div v-else class="grid">
      <UPageCard
        v-for="tour in profile.tours"
        :key="tour.id"
        variant="subtle"
        class="tour"
        :aria-label="`${tour.name}, completed`"
      >
        <div class="tour-body">
          <!-- UAvatar owns the broken-image fallback; the default slot replaces
               its initials fallback with the route glyph. -->
          <UAvatar :src="tour.image ?? undefined" :alt="tour.name" size="lg">
            <IconRoute class="tour-glyph" aria-hidden="true" />
          </UAvatar>

          <div class="copy">
            <h3>{{ tour.name }}</h3>
            <p>{{ tour.legs }} {{ tour.legs === 1 ? "leg" : "legs" }}</p>
            <span v-if="formatCompletedAt(tour.completedAt)" class="completed">
              Completed {{ formatCompletedAt(tour.completedAt) }}
            </span>
          </div>
        </div>
      </UPageCard>
    </div>
  </UPageCard>
</template>

<style scoped>
@layer components {
  .grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .tour {
    min-width: 0;
  }
  .tour-body {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 10px;
  }
  .tour-glyph {
    width: 20px;
    height: 20px;
    color: var(--pv-cyan);
  }
  .copy {
    min-width: 0;
  }
  .copy h3,
  .copy p {
    overflow-wrap: anywhere;
    margin: 0;
  }
  .copy h3 {
    color: var(--pv-ink);
    font-size: 13px;
    font-weight: 650;
  }
  .copy p {
    margin-top: 3px;
    color: var(--pv-ink-dim);
    font-size: 10px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }
  .completed {
    display: block;
    margin-top: 4px;
    color: var(--pv-green);
    font-size: 10px;
    font-weight: 650;
  }
  .empty {
    margin: 0;
    color: var(--pv-ink-dim);
    font-size: 13px;
  }
  @media (min-width: 640px) {
    .grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (min-width: 1200px) {
    .grid {
      grid-template-columns: repeat(6, minmax(0, 1fr));
    }
  }
}
</style>
