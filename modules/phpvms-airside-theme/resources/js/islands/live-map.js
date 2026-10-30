import { createDetailOverlay } from "./live-map/detail.js";
import { createFlightList } from "./live-map/list.js";
import { createLiveMap } from "./live-map/map.js";

/**
 * The live map island: mounts on `[data-airside-island="live-map"]`, polls
 * `GET /api/map/live` every max(interval, 5) seconds, and on selection loads
 * `GET /api/map/pirep/{id}`. Config comes from the child JSON script.
 */
const SELECTOR = '[data-airside-island="live-map"]';
const MIN_INTERVAL_SECONDS = 5;

/** @type {Map<Element, () => void>} */
const mounted = new Map();

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

/** @param {HTMLElement} root */
function mount(root) {
  const config = JSON.parse(
    root.querySelector(':scope > script[type="application/json"]')?.textContent ?? "{}",
  );
  const listEl = root.querySelector("[data-live-list]");
  const mapEl = root.querySelector("[data-live-map]");
  const overlayEl = root.querySelector("[data-live-overlay]");
  const countEl = root.querySelector("[data-live-count]");

  if (
    !(listEl instanceof HTMLElement) ||
    !(mapEl instanceof HTMLElement) ||
    !(overlayEl instanceof HTMLElement)
  ) {
    return () => {};
  }

  const state = {
    /** @type {any[]} */
    flights: [],
    loading: true,
    loadError: false,
    /** @type {string | null} */
    selected: null,
    /** @type {any} */
    detail: null,
    detailLoading: false,
    detailError: false,
  };
  let disposed = false;

  const list = createFlightList(root, listEl, select);
  const overlay = createDetailOverlay(root, overlayEl);
  const liveMap = createLiveMap(mapEl, config, select);

  function render() {
    if (countEl) {
      countEl.textContent = `${state.flights.length} ${state.flights.length === 1 ? "flight" : "flights"}`;
    }

    list.render(state);
    overlay.render(state);
  }

  async function fetchFlights() {
    try {
      const flights = await getJson("/api/map/live");

      if (disposed) {
        return;
      }

      state.flights = flights;
      state.loadError = false;
      liveMap.setFlights(flights);
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
    liveMap.select();
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
    liveMap.destroy();
  };
}

function mountAll() {
  document.querySelectorAll(SELECTOR).forEach((el) => {
    if (el instanceof HTMLElement && !mounted.has(el)) {
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
