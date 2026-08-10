<script setup lang="ts">
import type { HeaderAirline } from "./headerTypes";

defineProps<{ airline: HeaderAirline | null; appName: string }>();
</script>

<template>
  <div class="pv-header-airline" aria-label="Airline identity">
    <img v-if="airline?.logo" class="mark" :src="airline.logo" alt="" aria-hidden="true" />
    <span v-else class="mark fallback" aria-hidden="true">{{
      (airline?.icao ?? appName).slice(0, 2)
    }}</span>
    <span class="copy">
      <strong>{{ airline?.name ?? appName }}</strong>
      <small v-if="airline?.icao || airline?.iata">{{
        [airline.icao, airline.iata].filter(Boolean).join(" · ")
      }}</small>
    </span>
  </div>
</template>

<style scoped>
@layer components {
  .pv-header-airline {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 8px;
    color: var(--pv-ink);
  }
  .mark {
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    border-radius: var(--pv-radius-sm);
    object-fit: contain;
  }
  .fallback {
    display: grid;
    place-items: center;
    background: var(--pv-accent-soft);
    color: var(--pv-accent);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
  }
  .copy {
    display: grid;
    min-width: 0;
    line-height: 1.15;
  }
  strong,
  small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  strong {
    font-size: 12px;
    font-weight: 650;
  }
  small {
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 10px;
  }
  @media (max-width: 639px) {
    .copy {
      display: none;
    }
  }
}
</style>
