import { computed, onMounted, onUnmounted, shallowRef } from "vue";

export type ThemeMode = "light" | "dark" | "auto";

const storageKey = "skylight.theme";

function savedMode(): ThemeMode {
  try {
    const saved = localStorage.getItem(storageKey);
    return saved === "light" || saved === "dark" || saved === "auto" ? saved : "auto";
  } catch {
    return "auto";
  }
}

export function useHeaderTheme() {
  const mode = shallowRef<ThemeMode>("auto");
  const prefersDark = shallowRef(false);
  let media: MediaQueryList | undefined;

  const isDark = computed(
    () => mode.value === "dark" || (mode.value === "auto" && prefersDark.value),
  );

  function apply() {
    document.documentElement.classList.toggle("dark", isDark.value);
    document.documentElement.dataset.themeMode = mode.value;
  }

  function select(nextMode: ThemeMode) {
    mode.value = nextMode;
    try {
      localStorage.setItem(storageKey, nextMode);
    } catch {
      // Keep the selected mode for the current session when storage is unavailable.
    }
    apply();
  }

  function syncPreference(event: MediaQueryListEvent) {
    prefersDark.value = event.matches;
    if (mode.value === "auto") apply();
  }

  onMounted(() => {
    mode.value = savedMode();
    media = window.matchMedia("(prefers-color-scheme: dark)");
    prefersDark.value = media.matches;
    apply();
    media.addEventListener("change", syncPreference);
  });

  onUnmounted(() => media?.removeEventListener("change", syncPreference));

  return { isDark, mode, select };
}
