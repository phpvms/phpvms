<script setup lang="ts">
import { shallowReactive } from "vue";

defineProps<{ profile: App.Http.Data.ProfileData }>();
const failedImages = shallowReactive(new Set<number>());

function formatCompletedAt(value: string | null): string | null {
  return value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(new Date(value))
    : null;
}
</script>

<template>
  <section class="pv-profile-tours" aria-label="Tours completed">
    <header>
      <h2>TOURS COMPLETED</h2>
      <span
        ><strong>{{ profile.tours.length }}</strong> completed</span
      >
    </header>
    <p v-if="!profile.tours.length" class="empty">No tours completed yet.</p>
    <div v-else class="grid">
      <article
        v-for="(tour, index) in profile.tours"
        :key="tour.id"
        class="tour"
        :aria-label="`${tour.name}, completed`"
      >
        <div class="icon-tile">
          <img
            v-if="tour.image && !failedImages.has(index)"
            :src="tour.image"
            alt=""
            @error="failedImages.add(index)"
          />
          <svg v-else viewBox="0 0 24 24" aria-hidden="true">
            <path d="M2 16 22 9l-6 13-2-7-7-2Z" />
            <path d="M22 9 10 15" />
          </svg>
        </div>
        <div class="copy">
          <h3>{{ tour.name }}</h3>
          <p>{{ tour.legs }} {{ tour.legs === 1 ? "leg" : "legs" }}</p>
          <span v-if="formatCompletedAt(tour.completedAt)" class="completed"
            >Completed {{ formatCompletedAt(tour.completedAt) }}</span
          >
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .pv-profile-tours {
    margin-top: 20px;
  }
  .pv-profile-tours header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }
  .pv-profile-tours h2 {
    margin: 0;
    color: var(--pv-ink);
    font-family: var(--pv-font-mono);
    font-size: 12px;
    letter-spacing: 0.06em;
  }
  .pv-profile-tours header span {
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 12px;
  }
  .pv-profile-tours header strong {
    color: var(--pv-ink);
  }
  .grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .tour {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-lg);
    background: var(--pv-panel);
    padding: 12px;
  }
  .icon-tile {
    display: grid;
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    place-items: center;
    border: 1px solid color-mix(in srgb, var(--pv-cyan) 35%, var(--pv-line));
    border-radius: var(--pv-radius-sm);
    background: color-mix(in srgb, var(--pv-cyan) 10%, var(--pv-panel));
    color: var(--pv-cyan);
  }
  .icon-tile img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }
  .icon-tile svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
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
