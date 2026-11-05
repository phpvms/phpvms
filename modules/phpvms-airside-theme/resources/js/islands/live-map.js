import { createDetailOverlay } from "./live-map/detail.js";
import { createFlightList } from "./live-map/list.js";
import { createLiveMap } from "./live-map/map.js";

/**
 * The live map island: mounts on `[data-airside-island="live-map"]`, polls
 * `GET /api/map/live` every max(interval, 5) seconds, and on selection loads
 * `GET /api/map/pirep/{id}`. Config comes from the child JSON script.
 *
 * The counts (`[data-live-stat]`), the stage filter (`[data-live-filter]`) and
 * the search (`[data-live-search]`) are optional: the live map page has them,
 * the dashboard widget does not. Stages come from the config (`stages`, phase
 * value => stage), so the phase values are not repeated here.
 */
const SELECTOR = '[data-airside-island="live-map"]';
const MIN_INTERVAL_SECONDS = 5;
const AIRBORNE = new Set(["departing", "enroute", "arriving"]);

/** @type {Map<Element, () => void>} */
const mounted = new Map();

/**
 * Fold the sidebar to its rail while the map is open (`data-live-rail` on the root). It sets
 * TallStackUI's sidebar store directly, which does not save to localStorage, so the pilot's
 * own setting is read back on teardown; the toggle still works and still saves. On a full
 * page load the store reads localStorage when Alpine starts, so the rail waits for that.
 *
 * @returns {() => void} puts the pilot's setting back
 */
function railSidebar() {
  const store = () => window.Alpine?.store?.("tsui.side-bar");
  const fold = () => {
    const sidebar = store();

    if (sidebar) sidebar.open = false;
  };

  if (store()) {
    fold();
  } else {
    document.addEventListener("alpine:initialized", fold, { once: true });
  }

  return () => {
    document.removeEventListener("alpine:initialized", fold);
    const sidebar = store();

    if (sidebar) {
      const saved = localStorage.getItem("side-bar");

      sidebar.open = saved !== null ? Boolean(JSON.parse(saved)) : true;
    }
  };
}

/**
 * @param {string} url
 */
async function getJson(url) {
  const response = await fetch(url, { headers: { Accept: "application/json" } });

  if (!response.ok) {
    throw new Error(String(response.status));
  }

  return response.json();
}

/**
 * The text the search matches against: ident, route, pilot and aircraft.
 *
 * @param {any} flight MapLiveFlightData
 */
function searchText(flight) {
  return [
    flight.ident,
    flight.dptAirport?.icao,
    flight.arrAirport?.icao,
    flight.pilot?.name,
    flight.pilot?.ident,
    flight.aircraft?.registration,
    flight.aircraft?.name,
  ]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();
}

/** @param {HTMLElement} root */
function mount(root) {
  const config = JSON.parse(
    root.querySelector(':scope > script[type="application/json"]')?.textContent ?? "{}",
  );
  /** @type {Record<string, string>} */
  const stages = config.stages ?? {};
  const listEl = root.querySelector("[data-live-list]");
  const mapEl = root.querySelector("[data-live-map]");
  const overlayEl = root.querySelector("[data-live-overlay]");
  const countEl = root.querySelector("[data-live-count]");
  const searchEl = root.querySelector("[data-live-search]");
  const filterEls = [...root.querySelectorAll("[data-live-filter]")];
  const statEls = [...root.querySelectorAll("[data-live-stat]")];

  if (
    !(listEl instanceof HTMLElement) ||
    !(mapEl instanceof HTMLElement) ||
    !(overlayEl instanceof HTMLElement)
  ) {
    return () => {};
  }

  const state = {
    /** @type {any[]} every live flight from the last poll */
    all: [],
    /** @type {any[]} the flights the filter and search let through */
    flights: [],
    loading: true,
    loadError: false,
    /** @type {string | null} */
    selected: null,
    /** @type {any} */
    detail: null,
    detailLoading: false,
    detailError: false,
    filter: "all",
    query: "",
  };
  let disposed = false;
  const restoreSidebar = root.hasAttribute("data-live-rail") ? railSidebar() : () => {};

  /** @param {any} flight */
  const stageOf = (flight) => (flight?.phase ? (stages[flight.phase] ?? null) : null);

  const list = createFlightList(root, listEl, select, stageOf);
  const overlay = createDetailOverlay(root, overlayEl, deselect, (phase) => stages[phase] ?? null);
  const liveMap = createLiveMap(mapEl, config, select);

  function applyFilters() {
    const query = state.query.trim().toLowerCase();

    state.flights = state.all.filter(
      (flight) =>
        (state.filter === "all" || stageOf(flight) === state.filter) &&
        (query === "" || searchText(flight).includes(query)),
    );
    liveMap.setFlights(state.flights);
  }

  function renderStats() {
    const counts = { all: state.all.length, airborne: 0, arriving: 0, ground: 0 };

    for (const flight of state.all) {
      const stage = stageOf(flight);

      if (stage && AIRBORNE.has(stage)) counts.airborne++;
      if (stage === "arriving") counts.arriving++;
      if (stage === "ground") counts.ground++;
    }

    for (const el of statEls) {
      if (el instanceof HTMLElement) {
        el.textContent = String(counts[/** @type {keyof typeof counts} */ (el.dataset.liveStat)] ?? 0);
      }
    }
  }

  function render() {
    if (countEl) {
      countEl.textContent = `${state.all.length} ${state.all.length === 1 ? "flight" : "flights"}`;
    }

    renderStats();

    for (const el of filterEls) {
      if (el instanceof HTMLElement) {
        el.setAttribute("aria-pressed", String(el.dataset.liveFilter === state.filter));
      }
    }

    // On a phone the list makes way for the selected flight (group-data-selected/live on the panel).
    root.toggleAttribute("data-selected", state.selected !== null);
    list.render(state);
    overlay.render({
      ...state,
      live: state.all.find((flight) => flight.pirepId === state.selected) ?? null,
    });
  }

  async function fetchFlights() {
    try {
      const flights = await getJson("/api/map/live");

      if (disposed) {
        return;
      }

      state.all = flights;
      state.loadError = false;
      applyFilters();
    } catch {
      if (disposed) {
        return;
      }

      state.loadError = true;
    } finally {
      state.loading = false;
    }

    if (!disposed) {
      render();
    }
  }

  /** @param {string} pirepId */
  async function select(pirepId) {
    state.selected = pirepId;
    state.detail = null;
    state.detailError = false;
    state.detailLoading = true;
    liveMap.select(pirepId);
    render();

    try {
      const detail = await getJson(`/api/map/pirep/${encodeURIComponent(pirepId)}`);

      // A later selection replaces this one.
      if (disposed || state.selected !== pirepId) {
        return;
      }

      state.detail = detail;
      liveMap.showDetail(detail);
    } catch {
      if (disposed || state.selected !== pirepId) {
        return;
      }

      state.detailError = true;
    }

    state.detailLoading = false;
    render();
  }

  function deselect() {
    state.selected = null;
    state.detail = null;
    state.detailLoading = false;
    state.detailError = false;
    liveMap.select(null);
    render();
  }

  /** @param {Event} event */
  function onFilter(event) {
    const button = /** @type {HTMLElement} */ (event.currentTarget);

    state.filter = button.dataset.liveFilter ?? "all";
    applyFilters();
    render();
  }

  /** @param {Event} event */
  function onSearch(event) {
    state.query = /** @type {HTMLInputElement} */ (event.target).value;
    applyFilters();
    render();
  }

  /** @param {KeyboardEvent} event */
  function onKeydown(event) {
    if (event.key === "Escape" && state.selected) {
      deselect();
    }
  }

  filterEls.forEach((el) => el.addEventListener("click", onFilter));
  searchEl?.addEventListener("input", onSearch);
  document.addEventListener("keydown", onKeydown);

  render();
  fetchFlights();
  // A near-zero setting must not hammer the endpoint.
  const timer = setInterval(
    fetchFlights,
    Math.max(Number(config.updateIntervalSeconds) || 0, MIN_INTERVAL_SECONDS) * 1000,
  );

  return () => {
    disposed = true;
    clearInterval(timer);
    filterEls.forEach((el) => el.removeEventListener("click", onFilter));
    searchEl?.removeEventListener("input", onSearch);
    document.removeEventListener("keydown", onKeydown);
    liveMap.destroy();
    restoreSidebar();
    delete root.dataset.liveMounted;
  };
}

// The mark is on the element, not only in `mounted`: after a rebuild, wire:navigate can load this
// module a second time under a new file name, and that copy must not mount the same root again.
function mountAll() {
  document.querySelectorAll(SELECTOR).forEach((el) => {
    if (el instanceof HTMLElement && !mounted.has(el) && !el.dataset.liveMounted) {
      el.dataset.liveMounted = "true";
      mounted.set(el, mount(el));
    }
  });
}

function unmountAll() {
  mounted.forEach((teardown) => teardown());
  mounted.clear();
}

document.addEventListener("DOMContentLoaded", mountAll);
document.addEventListener("livewire:navigated", mountAll);
document.addEventListener("livewire:navigating", unmountAll);

if (document.readyState !== "loading") {
  mountAll();
}
