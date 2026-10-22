<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { nextTick, shallowRef, useTemplateRef } from "vue";
import AssignmentDrawer from "@/components/assignments/AssignmentDrawer.vue";
import FlightDetailPanel from "@/components/flights/FlightDetailPanel.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";

/**
 * A single flight's dispatch view. `FlightDetailPanel` carries this page's
 * header, so there is no `UPageHeader` here — the page only owns the bid drawer
 * and returning focus to whatever opened it.
 */
defineProps<{
  flight: App.Http.Data.FlightDetailData;
  policy: App.Http.Data.FlightDispatchPolicyData;
}>();

const drawer = useTemplateRef<InstanceType<typeof AssignmentDrawer>>("drawer");
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
  <UPage class="pv-flight-show" aria-label="Flight details">
    <UPageBody class="flight-show-body">
      <Link class="back-link" href="/flights">← Back to flight manifest</Link>
      <FlightDetailPanel :flight="flight" :policy="policy" @bid="openBid" />
    </UPageBody>

    <AssignmentDrawer ref="drawer" @closed="returnFocus" />
  </UPage>
</template>

<style scoped>
@layer components {
  .pv-flight-show {
    min-width: 0;
  }
  .flight-show-body {
    display: grid;
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
}
</style>
