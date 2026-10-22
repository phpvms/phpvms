<script setup lang="ts">
import HeaderClocks from "./HeaderClocks.vue";
import HeaderDuty from "./HeaderDuty.vue";
import HeaderMetar from "./HeaderMetar.vue";
import HeaderSector from "./HeaderSector.vue";
import type { ActiveSector, DutyState, HeaderUser } from "./headerTypes";
import type { MetarState } from "./useMetar";
import UDrawer from "@nuxt/ui/components/Drawer.vue";

defineProps<{
  duty: DutyState;
  local: string | null;
  metar: MetarState;
  open: boolean;
  sector: ActiveSector | null;
  timezone: string | null;
  user: HeaderUser;
  utc: string;
}>();
const emit = defineEmits<{ retry: []; "update:open": [open: boolean] }>();
</script>

<template>
  <UDrawer
    :open="open"
    direction="bottom"
    title="Dispatch status"
    close
    @update:open="emit('update:open', $event)"
  >
    <template #content>
      <section class="pv-header-status-drawer" aria-label="Dispatch status">
        <div class="pilot">
          <strong>{{ user.name }}</strong
          ><span
            >{{ user.ident }}<template v-if="user.callsign"> · {{ user.callsign }}</template></span
          >
        </div>
        <HeaderSector :sector="sector" />
        <HeaderDuty :duty="duty" />
        <HeaderMetar :state="metar" @retry="emit('retry')" />
        <HeaderClocks :utc :local :timezone />
      </section>
    </template>
  </UDrawer>
</template>

<style scoped>
@layer components {
  .pv-header-status-drawer {
    display: grid;
    gap: 18px;
    padding: 8px 20px 32px;
    color: var(--pv-ink);
  }
  .pilot {
    display: grid;
    gap: 2px;
  }
  .pilot strong {
    font-size: 16px;
  }
  .pilot span {
    color: var(--pv-ink-dim);
    font-family: var(--pv-font-mono);
    font-size: 12px;
  }
}
</style>
