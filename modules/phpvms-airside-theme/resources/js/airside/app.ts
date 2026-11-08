/*
 * airside's Alpine components. Alpine itself comes from Livewire (and
 * TallStackUI's script), so this file only registers components on
 * `alpine:init`. It must not bundle Alpine. Theme mode, the sidebar and the
 * toasts are TallStackUI's own.
 */

import { dashboardLayout } from "./dashboard-layout";
import { livewireDrawer } from "./livewire-drawer";
import { mapDots } from "./map-dots";
import { selectMenu } from "./select-menu";

interface AirportResult {
  id: string;
  description: string;
}

document.addEventListener("alpine:init", () => {
  const { Alpine } = window;

  Alpine.data("mapDots", mapDots);
  Alpine.data("livewireDrawer", livewireDrawer);
  Alpine.data("dashboardLayout", dashboardLayout);
  Alpine.data("selectMenu", selectMenu);

  /**
   * HTML-escape a plain string. TallStackUI's toast renders its title with
   * x-html, so text from a `toast` event goes through this first.
   */
  Alpine.magic("escapeHtml", () => (value: unknown): string =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[char] as string,
    ),
  );

  /**
   * The dispatch header: UTC and station-local clocks, ticking at each minute
   * boundary.
   */
  Alpine.data("airsideClocks", (timezone: string | null = null) => ({
    timezone,
    now: new Date(),
    timer: undefined as ReturnType<typeof setTimeout> | undefined,

    init() {
      this.tick();
    },

    destroy() {
      clearTimeout(this.timer);
    },

    tick() {
      this.now = new Date();
      this.timer = setTimeout(() => this.tick(), 60_000 - (Date.now() % 60_000) + 25);
    },

    get utc() {
      const pad = (value: number): string => String(value).padStart(2, "0");

      return `${pad(this.now.getUTCHours())}:${pad(this.now.getUTCMinutes())}Z`;
    },

    get local() {
      if (!this.timezone) {
        return null;
      }

      try {
        return new Intl.DateTimeFormat(undefined, {
          hour: "2-digit",
          hourCycle: "h23",
          minute: "2-digit",
          timeZone: this.timezone,
        }).format(this.now);
      } catch {
        return null;
      }
    },
  }));

  /**
   * Home airport search for the registration form. Posts the chosen airport's
   * id (the API's `id`), searching `/api/airports/search` as the old form did.
   */
  Alpine.data("airsideAirportSearch", (hubsOnly = false) => ({
    query: "",
    results: [] as AirportResult[],
    selected: "",
    selectedLabel: "",

    async search() {
      if (this.query.trim().length < 2) {
        this.results = [];

        return;
      }

      const url = new URL("/api/airports/search", window.location.origin);

      url.search = new URLSearchParams({
        search: this.query,
        hubs: hubsOnly ? "1" : "0",
        page: "1",
        orderBy: "id",
        sortedBy: "asc",
      }).toString();

      try {
        const response = await fetch(url, { headers: { Accept: "application/json" } });

        this.results = response.ok ? ((await response.json()).data ?? []) : [];
      } catch {
        this.results = [];
      }
    },

    choose(airport: AirportResult) {
      this.selected = airport.id;
      this.selectedLabel = airport.description;
      this.query = "";
      this.results = [];
    },
  }));
});
