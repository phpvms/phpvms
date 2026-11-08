/**
 * The dashboard board's layout, kept in the browser: `x-data="dashboardLayout"`
 * on the DashboardBoard root. On init it sends the stored layout to the
 * component (`loadLayout`); each `dashboard-layout-changed` from the component
 * is stored again. Storage can be blocked; the layout then lasts for the page.
 *
 * Widgets are rendered after the layout arrives, so an island that appears
 * later (the route map) is mounted by the same event the islands use on
 * navigation.
 */
const STORAGE_KEY = "airside.dashboard.layout.v1";

interface Layout {
  grid: unknown;
  sidebar: unknown;
}

interface Wire {
  loadLayout(layout: Layout | null): void;
  $on(event: string, callback: (detail: Layout) => void): void;
}

function read(): Layout | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);

    return raw ? (JSON.parse(raw) as Layout) : null;
  } catch {
    return null;
  }
}

function write(layout: Layout): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(layout));
  } catch {
    // Storage blocked: keep the layout for this page only.
  }
}

export function dashboardLayout() {
  let observer: MutationObserver | null = null;

  return {
    init(this: { $wire: Wire; $el: HTMLElement }) {
      const root = this.$el;

      this.$wire.$on("dashboard-layout-changed", ({ grid, sidebar }) => write({ grid, sidebar }));
      setTimeout(() => this.$wire.loadLayout(read()), 0);

      const seen = new WeakSet<Element>();
      observer = new MutationObserver(() => {
        const fresh = [...root.querySelectorAll("[data-airside-island]")].filter((el) => !seen.has(el));

        if (fresh.length) {
          fresh.forEach((el) => seen.add(el));
          document.dispatchEvent(new CustomEvent("livewire:navigated"));
        }
      });
      observer.observe(root, { childList: true, subtree: true });
    },

    destroy() {
      observer?.disconnect();
    },
  };
}
