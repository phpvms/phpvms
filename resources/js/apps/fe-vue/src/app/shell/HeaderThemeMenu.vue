<script setup lang="ts">
import type { ThemeMode } from "./useHeaderTheme";
import UButton from "@nuxt/ui/components/Button.vue";
import UDropdownMenu from "@nuxt/ui/components/DropdownMenu.vue";

const props = defineProps<{ mode: ThemeMode }>();
const emit = defineEmits<{ select: [mode: ThemeMode] }>();

const modes: ThemeMode[] = ["light", "dark", "auto"];
</script>

<template>
  <UDropdownMenu
    :items="
      modes.map((item) => ({
        label: item[0].toUpperCase() + item.slice(1),
        type: 'checkbox',
        checked: item === props.mode,
        onSelect: () => emit('select', item),
      }))
    "
  >
    <UButton
      class="pv-header-theme"
      color="neutral"
      variant="ghost"
      size="sm"
      :aria-label="`Theme: ${mode}`"
    >
      <span aria-hidden="true">{{ mode === "auto" ? "◐" : mode === "dark" ? "☾" : "☀" }}</span>
      <span class="mode">{{ mode }}</span>
    </UButton>
  </UDropdownMenu>
</template>

<style scoped>
@layer components {
  .pv-header-theme {
    color: var(--pv-ink-dim);
    text-transform: capitalize;
  }
  .pv-header-theme:hover {
    color: var(--pv-ink);
  }
  .mode {
    font-size: 11px;
  }
  .pv-header-theme:focus-visible {
    outline: 2px solid var(--pv-accent);
    outline-offset: 2px;
  }
  @media (max-width: 639px) {
    .mode {
      display: none;
    }
  }
}
</style>
