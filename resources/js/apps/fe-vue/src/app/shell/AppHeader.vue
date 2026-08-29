<script setup lang="ts">
import { computed, shallowRef } from "vue";
import HeaderAirline from "./HeaderAirline.vue";
import HeaderClocks from "./HeaderClocks.vue";
import HeaderDuty from "./HeaderDuty.vue";
import HeaderMetar from "./HeaderMetar.vue";
import HeaderSector from "./HeaderSector.vue";
import HeaderStatusDrawer from "./HeaderStatusDrawer.vue";
import type { DutyState } from "./headerTypes";
import { useAppChrome } from "./useAppChrome";
import { useHeaderClocks } from "./useHeaderClocks";
import { useMetar } from "./useMetar";
import UButton from "@nuxt/ui/components/Button.vue";

const { appName, pilotChrome, user } = useAppChrome();
const station = computed(() => pilotChrome.value?.station ?? null);
const duty = computed<DutyState>(
  () => pilotChrome.value?.duty ?? { state: "off_duty", label: "Off duty", color: "neutral" },
);
const sector = computed(() => pilotChrome.value?.activeSector ?? null);
const { state: metar, retry } = useMetar(station);
const { local, timezone, utc } = useHeaderClocks(station);
const isStatusDrawerOpen = shallowRef(false);
</script>

<template>
  <div class="pv-dispatch-header">
    <div class="primary-row">
      <HeaderAirline :airline="user?.airline ?? null" :app-name="appName" />
      <div v-if="user" class="mobile-sector"><HeaderSector :sector="sector" compact /></div>
      <div class="mobile-utc">
        <time :datetime="utc">{{ utc }}</time>
      </div>
      <div class="primary-actions">
        <UButton
          v-if="user"
          class="pv-header-status-trigger"
          color="neutral"
          variant="ghost"
          size="sm"
          aria-label="Open dispatch status"
          @click="isStatusDrawerOpen = true"
        >
          Status
        </UButton>
      </div>
    </div>

    <div v-if="user" class="operations-row">
      <HeaderSector :sector="sector" />
      <HeaderDuty :duty="duty" />
      <HeaderMetar :state="metar" @retry="retry" />
      <HeaderClocks :utc :local :timezone />
    </div>
  </div>

  <HeaderStatusDrawer
    v-if="user"
    v-model:open="isStatusDrawerOpen"
    :duty
    :local
    :metar
    :sector
    :timezone
    :user
    :utc
    @retry="retry"
  />
</template>

<style scoped>
@layer components {
  .pv-dispatch-header {
    display: grid;
    width: 100%;
    min-width: 0;
    color: var(--pv-ink);
  }
  .primary-row,
  .operations-row {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 14px;
  }
  .primary-actions {
    display: flex;
    min-width: 0;
    align-items: center;
    margin-left: auto;
  }
  .mobile-sector,
  .mobile-utc {
    display: none;
  }
  .pv-header-status-trigger {
    display: none !important;
  }
  .operations-row {
    min-height: 36px;
    border-top: 1px solid var(--pv-line);
    padding: 6px 0;
  }
  .operations-row > :nth-child(1) {
    flex: 0 1 150px;
  }
  .operations-row > :nth-child(3) {
    flex: 1 1 320px;
  }
  @media (min-width: 1024px) {
    .pv-dispatch-header {
      display: flex;
      align-items: center;
      gap: 18px;
    }
    .primary-row {
      display: contents;
    }
    .operations-row {
      display: contents;
    }
    .operations-row > :nth-child(1) {
      order: 1;
      flex: 0 1 130px;
    }
    .operations-row > :nth-child(2) {
      order: 2;
    }
    .operations-row > :nth-child(3) {
      order: 3;
      flex: 1 1 240px;
    }
    .operations-row > :nth-child(4) {
      order: 4;
      flex: 0 0 auto;
    }
    .primary-actions {
      order: 5;
    }
  }
  @media (max-width: 639px) {
    .pv-dispatch-header {
      display: flex;
      align-items: center;
    }
    .primary-row {
      width: 100%;
      gap: 8px;
    }
    .operations-row {
      display: none;
    }
    .mobile-sector {
      display: block;
      min-width: 0;
      flex: 1 1 auto;
    }
    .mobile-utc {
      display: block;
      color: var(--pv-ink);
      font-family: var(--pv-font-mono);
      font-size: 11px;
      white-space: nowrap;
    }
    .pv-header-status-trigger {
      display: inline-flex !important;
    }
    .primary-actions {
      flex: 0 0 auto;
    }
    .pv-header-status-trigger:focus-visible {
      outline: 2px solid var(--pv-accent);
      outline-offset: 2px;
    }
  }
}
</style>
