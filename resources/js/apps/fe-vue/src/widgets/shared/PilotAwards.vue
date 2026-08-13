<script setup lang="ts">
import { shallowReactive } from "vue";

defineProps<{ awards: App.Http.Data.AwardData[] }>();
const failedImages = shallowReactive(new Set<number>());
</script>

<template>
  <section class="pv-pilot-awards" aria-label="Awards">
    <header>
      <h2>AWARDS &amp; MERITS</h2>
      <span
        ><strong>{{ awards.length }}</strong> earned</span
      >
    </header>
    <p v-if="!awards.length" class="empty">No awards earned yet.</p>
    <div v-else class="grid">
      <article
        v-for="(award, index) in awards"
        :key="`${award.name}-${index}`"
        class="award"
        :aria-label="`${award.name}, earned`"
      >
        <div class="icon-tile">
          <img
            v-if="award.image && !failedImages.has(index)"
            :src="award.image"
            alt=""
            @error="failedImages.add(index)"
          />
          <svg v-else viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="9" r="6" />
            <path d="M8.5 14 7 22l5-3 5 3-1.5-8" />
          </svg>
        </div>
        <div class="copy">
          <h3>{{ award.name }}</h3>
          <p>{{ award.description ?? "Qualifier unavailable" }}</p>
          <span class="earned">Earned</span>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .pv-pilot-awards {
    margin-top: 20px;
  }
  .pv-pilot-awards header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }
  .pv-pilot-awards h2 {
    margin: 0;
    color: var(--pv-ink);
    font-family: var(--pv-font-mono);
    font-size: 12px;
    letter-spacing: 0.06em;
  }
  .pv-pilot-awards header span {
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 12px;
  }
  .pv-pilot-awards header strong {
    color: var(--pv-ink);
  }
  .grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .award {
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
  .earned {
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
