<script setup lang="ts">
import { computed } from "vue";

interface PilotIdentUser {
  ident: string | null;
  name: string;
  avatar?: string | null;
  airline: App.Http.Data.AirlineIdentityData | App.Http.Data.AirlineRefData | null;
  rank?: App.Http.Data.RankData | null;
}

const props = withDefaults(
  defineProps<{
    user: PilotIdentUser;
    href?: string;
    size?: "md" | "lg";
  }>(),
  { size: "md" },
);

const initials = computed(() =>
  props.user.name
    .trim()
    .split(/\s+/)
    .map((part) => part[0])
    .filter(Boolean)
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);
const context = computed(() =>
  [props.user.rank?.name, props.user.airline?.name].filter(Boolean).join(" · "),
);
const userSize = computed(() => (props.size === "lg" ? "3xl" : "xl"));
const avatarProps = computed(() => ({
  src: props.user.avatar ?? undefined,
  alt: props.user.name,
  text: initials.value,
  ui: { root: "border border-line-strong bg-panel-inset" },
}));
</script>

<template>
  <UUser
    class="pilot-ident-header"
    :to="href"
    :size="userSize"
    :avatar="avatarProps"
    :ui="{
      wrapper: 'min-w-0',
      name: 'flex min-w-0 items-baseline gap-2 leading-tight',
      description: 'mt-0.5 truncate text-ink-dim',
    }"
  >
    <template #name>
      <strong class="pilot-ident-header__ident">{{ user.ident ?? "—" }}</strong>
      <span class="truncate">{{ user.name }}</span>
    </template>

    <template v-if="context" #description>{{ context }}</template>
  </UUser>
</template>

<style scoped>
.pilot-ident-header {
  max-width: 100%;
  color: var(--pv-ink);
}
.pilot-ident-header__ident {
  flex: none;
  color: var(--pv-accent);
  font-family: var(--pv-font-mono);
  font-weight: 750;
}
</style>
