<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import PirepCard from "@/components/pireps/PirepCard.vue";
import IconLogbook from "~icons/tabler/notebook";
import UBadge from "@nuxt/ui/components/Badge.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";
import UPageList from "@nuxt/ui/components/PageList.vue";
import UPagination from "@nuxt/ui/components/Pagination.vue";
import UEmpty from "@nuxt/ui/components/Empty.vue";

/**
 * Logbook — the pilot's PIREP list. Rows are PirepListItemData, GENERATED from
 * the PHP DTO (App.Http.Data.* ambient global). The page owns paging; the card
 * owns how a PIREP looks.
 */
const props = defineProps<{
  pireps: App.Http.Data.PirepListItemData[];
  pagination: { currentPage: number; lastPage: number; total: number; perPage: number };
}>();

function goto(page: number): void {
  if (page < 1 || page > props.pagination.lastPage) return;
  router.get("/pireps", { page }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
  <UPage class="pv-logbook" aria-label="Logbook">
    <UPageHeader
      class="logbook-header"
      headline="Logbook"
      title="My PIREPs"
      description="Every flight you have filed, most recent first."
    >
      <template #links>
        <UBadge color="neutral" variant="subtle" size="lg">{{ pagination.total }} total</UBadge>
      </template>
    </UPageHeader>

    <UPageBody>
      <!-- UPageList renders role="list", so each card has to claim listitem. -->
      <UPageList v-if="pireps.length" class="pirep-list">
        <PirepCard v-for="pirep in pireps" :key="pirep.id" role="listitem" :pirep="pirep" />
      </UPageList>

      <UEmpty
        v-else
        :icon="IconLogbook"
        title="No PIREPs yet"
        description="Fly a flight and it will show up in your logbook."
      />

      <UPagination
        v-if="pagination.lastPage > 1"
        class="logbook-pager"
        :page="pagination.currentPage"
        :total="pagination.total"
        :items-per-page="pagination.perPage"
        @update:page="goto"
      />
    </UPageBody>
  </UPage>
</template>

<style scoped>
@layer components {
  .pv-logbook {
    min-width: 0;
  }
  .logbook-header {
    margin-bottom: 16px;
  }
  .pirep-list {
    gap: 8px;
  }
  .logbook-pager {
    justify-content: center;
    margin-top: 14px;
  }
}
</style>
