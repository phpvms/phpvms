<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import DashboardBoard from "./DashboardBoard.vue";
import DashboardPilotHeader from "./DashboardPilotHeader.vue";
import DashboardToolbar from "./DashboardToolbar.vue";
import { useDashboardLayout } from "./useDashboardLayout";

const page = usePage();
const name = computed(() => (page.props.name as string) ?? "Pilot");
const rank = computed(() => page.props.rank as { from: string } | null);
const station = computed(() => (page.props.currentAirport as string | null) ?? null);
const onLeave = computed(() => Boolean(page.props.onLeave));
const initials = computed(() =>
  name.value
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);
const { layout, editing, availableToAdd, addWidget, removeWidget, resetLayout, toggleEdit } =
  useDashboardLayout();
</script>

<template>
  <div class="pv-dashboard">
    <DashboardPilotHeader :initials :name :on-leave="onLeave" :rank :station />
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
  </div>
</template>
