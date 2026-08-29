<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import type { AppUser } from "@/app/shell/useAppChrome";
import DashboardBoard from "./DashboardBoard.vue";
import DashboardPilotHeader from "./DashboardPilotHeader.vue";
import DashboardToolbar from "./DashboardToolbar.vue";
import { useDashboardLayout } from "./useDashboardLayout";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

type DashboardPageProps = App.Http.Data.DashboardData &
  Record<string, unknown> & {
    auth?: { user: AppUser | null };
  };

const page = usePage<DashboardPageProps>();
const user = computed(() => page.props.auth?.user ?? null);
const name = computed(() => user.value?.name ?? "Pilot");
const initials = computed(() =>
  name.value
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);
const dashboard = computed<App.Http.Data.DashboardData>(() => page.props);
const { layout, editing, availableToAdd, addWidget, removeWidget, resetLayout, toggleEdit } =
  useDashboardLayout();
</script>

<template>
  <UPage class="pv-dashboard">
    <UPageHeader>
      <DashboardPilotHeader :dashboard :initials :user />
    </UPageHeader>

    <DashboardToolbar
      :available-widgets="availableToAdd"
      :editing
      @add-widget="addWidget"
      @reset="resetLayout"
      @toggle-editing="toggleEdit"
    />
    <DashboardBoard
      v-model="layout"
      :editing
      :page-props="page.props as Record<string, unknown>"
      @remove-widget="removeWidget"
    />
  </UPage>
</template>
