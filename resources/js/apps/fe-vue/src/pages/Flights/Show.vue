<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { nextTick, shallowRef, useTemplateRef } from "vue";
import FlightBidDrawer from "@/features/flights/FlightBidDrawer.vue";
import FlightDetailPanel from "@/features/flights/FlightDetailPanel.vue";

defineProps<{
  flight: App.Http.Data.FlightDetailData;
  policy: App.Http.Data.FlightDispatchPolicyData;
}>();

const drawer = useTemplateRef<InstanceType<typeof FlightBidDrawer>>("drawer");
const invokingControl = shallowRef<HTMLElement | null>(null);

function openBid(flightId: string, event: MouseEvent) {
  invokingControl.value = event.currentTarget as HTMLElement;
  drawer.value?.show(flightId);
}

async function returnFocus() {
  await nextTick();
  invokingControl.value?.focus();
}
</script>

<template>
  <section class="pv-flight-show" aria-label="Flight details">
    <Link class="back-link" href="/flights">← Back to flight manifest</Link>
    <FlightDetailPanel :flight="flight" :policy="policy" @bid="openBid" />
    <FlightBidDrawer ref="drawer" @closed="returnFocus" />
  </section>
</template>

<style scoped>
.pv-flight-show {
  display: grid;
  min-width: 0;
  gap: 14px;
}
.back-link {
  width: fit-content;
  color: var(--pv-accent);
  font-size: calc(12px * var(--pv-type-scale));
  font-weight: 650;
  text-decoration: none;
}
.back-link:hover {
  text-decoration: underline;
}
</style>
