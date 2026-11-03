/**
 * Command palette (cmd/ctrl+K) behaviour. The overlay itself is the
 * App\Livewire\Filament\CommandPalette component, rendered once per page on
 * PanelsRenderHook::BODY_END; this file owns opening it, keyboard movement
 * and the recents list.
 *
 * The keydown listener lives on `document` and is registered once at module
 * load (guarded for hot reload), so it survives Livewire SPA navigations
 * without being re-bound. It only dispatches `cmdk-open`; the component
 * (re-mounted on each navigation) listens for that on `window`.
 */

export const RECENT_KEY = "phpvms.admin.palette.recent";
export const RECENT_LIMIT = 8;

export function readRecents(storage = window.localStorage) {
  try {
    const parsed = JSON.parse(storage.getItem(RECENT_KEY) ?? "[]");
    return Array.isArray(parsed) ? parsed.filter((entry) => entry?.url) : [];
  } catch {
    return [];
  }
}

/**
 * Newest first, one entry per url, capped at RECENT_LIMIT.
 */
export function pushRecent(entry, storage = window.localStorage) {
  const next = [
    entry,
    ...readRecents(storage).filter((existing) => existing.url !== entry.url),
  ].slice(0, RECENT_LIMIT);

  try {
    storage.setItem(RECENT_KEY, JSON.stringify(next));
  } catch {
    // Storage full or blocked: recents are a convenience, not state.
  }

  return next;
}

export function isOpenShortcut(event) {
  return (event.metaKey || event.ctrlKey) && !event.altKey && event.key.toLowerCase() === "k";
}

if (!window.__phpvmsCommandPaletteBound) {
  window.__phpvmsCommandPaletteBound = true;

  document.addEventListener("keydown", (event) => {
    if (!isOpenShortcut(event)) {
      return;
    }

    event.preventDefault();
    window.dispatchEvent(new CustomEvent("cmdk-open"));
  });
}

document.addEventListener("alpine:init", () => {
  window.Alpine.data("phpvmsCommandPalette", ({ spa = false } = {}) => ({
    open: false,
    active: 0,
    recents: [],

    init() {
      this.$watch("$wire.query", () => {
        this.active = 0;
      });
    },

    show() {
      if (this.open) {
        // Already open: just put the caret back in the field.
        this.focusInput();
        return;
      }

      this.recents = readRecents();
      this.active = 0;

      // Only reset a non-empty query: a needless Livewire round trip can morph
      // the DOM and race the focus below.
      if (this.$wire.query !== "") {
        this.$wire.query = "";
        this.$refs.input.value = "";
      }

      this.open = true;
      this.focusInput();
    },

    /**
     * Wait for the panel to be visible (x-show) and painted before focusing.
     */
    focusInput() {
      this.$nextTick(() =>
        requestAnimationFrame(() => {
          this.$refs.input.focus();
          this.$refs.input.select();
        }),
      );
    },

    close() {
      this.open = false;
    },

    items() {
      return Array.from(this.$refs.results.querySelectorAll("[data-cmdk-item]"));
    },

    move(step) {
      const count = this.items().length;
      if (count === 0) {
        return;
      }

      this.active = (this.active + step + count) % count;
      this.items()[this.active]?.scrollIntoView({ block: "nearest" });
    },

    activate() {
      this.items()[this.active]?.click();
    },

    /**
     * Modified clicks (new tab/window) are left to the browser.
     */
    choose(event, element) {
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
        return;
      }

      event.preventDefault();

      const { title, url, category } = element.dataset;
      pushRecent({ title, url, category });
      this.close();

      if (spa && window.Livewire?.navigate) {
        window.Livewire.navigate(url);
      } else {
        window.location.assign(url);
      }
    },
  }));
});
