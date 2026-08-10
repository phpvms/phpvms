<script setup lang="ts">
defineProps<{
  initials: string;
  name: string;
  onLeave: boolean;
  rank: { from: string } | null;
  station: string | null;
}>();
</script>

<template>
  <section class="pilot pv-dashboard-pilot" aria-label="Pilot">
    <div class="avatar">{{ initials }}</div>
    <div class="who">
      <div class="line1">
        <h1 class="name">{{ name }}</h1>
        <span v-if="rank" class="rankchip">{{ rank.from }}</span>
        <span v-if="onLeave" class="leavechip">{{ $t("ui.on_leave") }}</span>
      </div>
      <div class="line2">
        <span v-if="station" class="loc">
          <svg viewBox="0 0 24 24" class="pin" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z" />
            <circle cx="12" cy="10" r="2.5" />
          </svg>
          {{ station }}
        </span>
      </div>
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .pilot {
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--pv-panel);
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-xl);
    padding: 16px 18px;
    margin-bottom: 16px;
  }
  .avatar {
    width: 48px;
    height: 48px;
    border-radius: var(--pv-radius-lg);
    background: color-mix(in srgb, var(--pv-accent) 14%, var(--pv-panel));
    color: var(--pv-accent);
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
  }
  .line1 {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .name {
    font-size: 18px;
    font-weight: 600;
    color: var(--pv-ink);
  }
  .rankchip {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: var(--pv-radius-full);
    color: var(--pv-accent);
    background: var(--pv-accent-soft);
  }
  .leavechip {
    font-size: 11px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: var(--pv-radius-full);
    color: var(--pv-amber);
    background: color-mix(in srgb, var(--pv-amber) 12%, transparent);
  }
  .line2 {
    margin-top: 4px;
  }
  .loc {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
  }
  .pin {
    width: 13px;
    height: 13px;
  }
}
</style>
