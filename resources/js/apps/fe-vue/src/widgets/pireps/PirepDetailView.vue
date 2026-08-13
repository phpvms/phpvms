<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import PirepContent from "./PirepContent.vue";
import PirepDetails from "./PirepDetails.vue";
import PirepSummary from "./PirepSummary.vue";

defineProps<{ pirep: App.Http.Data.PirepData }>();
</script>

<template>
  <section class="pv-pirep-detail" aria-label="PIREP detail">
    <Link href="/pireps" class="back">← Logbook</Link>
    <PirepSummary :pirep />
    <div class="grid">
      <PirepContent :pirep />
      <PirepDetails :pirep />
    </div>
  </section>
</template>

<style scoped>
@layer components {
  .back {
    font-size: 12px;
    color: var(--pv-ink-dim);
    text-decoration: none;
  }
  .back:hover {
    color: var(--pv-accent);
  }
  .grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-top: 12px;
  }
  @media (min-width: 860px) {
    .grid {
      grid-template-columns: 2fr 1fr;
    }
  }
}
</style>
