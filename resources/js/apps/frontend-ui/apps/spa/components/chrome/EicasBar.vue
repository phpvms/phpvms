<script setup lang="ts">
import { computed, ref } from "vue";
import ZuluClock from "./ZuluClock.vue";

/**
 * Header status strip (EICAS band): a time-of-day greeting on the left; the live
 * Zulu clock + pilot avatar on the right. Fills PvLayout's `header` region. The
 * avatar mirrors the Filament admin's header avatar affordance.
 */
const props = defineProps<{ name: string; station?: string | null; avatar?: string | null }>();

const greeting = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return "MORNING";
  if (h < 18) return "AFTERNOON";
  return "EVENING";
});

const surname = computed(() => (props.name || "").split(" ").pop()?.toUpperCase() ?? props.name);
const initials = computed(() =>
  (props.name || "?")
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);
const imgFailed = ref(false);
</script>

<template>
  <span class="greeting">
    {{ greeting }}, CAPT.&nbsp;<strong>{{ surname }}</strong>
  </span>

  <div class="right">
    <ZuluClock :station="station" />
    <div class="avatar" :title="name">
      <img v-if="avatar && !imgFailed" :src="avatar" :alt="name" @error="imgFailed = true" />
      <span v-else class="initials">{{ initials }}</span>
    </div>
  </div>
</template>

<style scoped>
.greeting {
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
}
.greeting strong {
  color: var(--pv-ink);
  font-weight: 500;
}
.right {
  display: flex;
  align-items: center;
  gap: 16px;
}
.avatar {
  width: 30px;
  height: 30px;
  border-radius: var(--pv-radius-full);
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
  font-family: var(--pv-font-mono);
  font-size: calc(10px * var(--pv-type-scale));
  font-weight: 500;
  color: var(--pv-ink-dim);
}
</style>
