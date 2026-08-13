<script setup lang="ts">
import { shallowRef } from "vue";
import type { AppUser } from "./useAppChrome";

defineProps<{ initials: string; user: AppUser | null }>();
const imageFailed = shallowRef(false);
</script>

<template>
  <div class="mini pv-pilot-status">
    <div class="ma">
      <img
        v-if="user?.avatar && !imageFailed"
        :src="user.avatar"
        :alt="user.name"
        @error="imageFailed = true"
      />
      <span v-else>{{ initials }}</span>
    </div>
    <div class="mt">
      <div class="mn">{{ user?.name ?? $t("ui.role_pilot") }}</div>
      <div class="micro ms">{{ $t("ui.on_duty") }}</div>
    </div>
    <span class="dot" />
  </div>
</template>

<style scoped>
@layer components {
  .mini {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-top: 1px solid var(--pv-line);
    flex-shrink: 0;
  }
  .ma {
    width: 32px;
    height: 32px;
    border-radius: var(--pv-radius-full);
    overflow: hidden;
    flex-shrink: 0;
    background: color-mix(in srgb, var(--pv-accent) 14%, var(--pv-panel));
    color: var(--pv-accent);
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 600;
  }
  .ma img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .mt {
    flex: 1;
    min-width: 0;
  }
  .mn {
    font-size: 12px;
    font-weight: 500;
    color: var(--pv-ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .ms {
    color: var(--pv-green) !important;
  }
  .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--pv-green);
    flex-shrink: 0;
  }
}
</style>
