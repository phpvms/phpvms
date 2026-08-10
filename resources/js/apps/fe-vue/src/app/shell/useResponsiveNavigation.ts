import { nextTick, readonly, shallowRef, watch, type ShallowRef } from "vue";
import { useMediaQuery } from "@vueuse/core";

interface ResponsiveNavigationElements {
  navigationRegion: Readonly<ShallowRef<HTMLElement | null>>;
  navigationToggle: Readonly<ShallowRef<HTMLButtonElement | null>>;
}

export function useResponsiveNavigation({
  navigationRegion,
  navigationToggle,
}: ResponsiveNavigationElements) {
  const isMobile = useMediaQuery("(max-width: 1023px)");
  const isOpen = shallowRef(false);

  watch(isMobile, (mobile) => {
    if (!mobile) close(false);
  });

  async function toggle() {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
      await nextTick();
      navigationRegion.value?.querySelector<HTMLElement>("a")?.focus();
    }
  }

  function close(restoreFocus = true) {
    if (!isOpen.value) return;

    isOpen.value = false;
    if (restoreFocus) void nextTick(() => navigationToggle.value?.focus());
  }

  function closeFromNavigation(event: MouseEvent) {
    if (isMobile.value && event.target instanceof Element && event.target.closest("a")) {
      close(false);
    }
  }

  return {
    isMobile: readonly(isMobile),
    isOpen: readonly(isOpen),
    toggle,
    close,
    closeFromNavigation,
  };
}
