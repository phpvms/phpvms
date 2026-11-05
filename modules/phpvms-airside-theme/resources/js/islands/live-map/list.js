import { cloneTemplate, formatAltitude, setField } from "./dom.js";

/**
 * The live flights list: one row per flight, plus the empty and error states
 * (LiveFlightList). Rows are kept by pirep id between polls, so a poll does not
 * rebuild the list or lose its scroll position.
 *
 * @param {HTMLElement} root the island root
 * @param {HTMLElement} container the list element
 * @param {(pirepId: string) => void} onSelect
 * @param {(flight: any) => string | null} stageOf the flight's stage, from the config's stage map
 */
export function createFlightList(root, container, onSelect, stageOf) {
  /** @type {Map<string, HTMLElement>} */
  const rows = new Map();
  /** @type {HTMLElement | null} */
  let listbox = null;
  /** @type {HTMLElement | null} */
  let notice = null;

  // Start from an empty container, whatever an earlier mount left in it.
  container.replaceChildren();

  function clearNotice() {
    notice?.remove();
    notice = null;
  }

  function clearRows() {
    listbox?.remove();
    listbox = null;
    rows.clear();
  }

  /**
   * `all` is every live flight; `flights` is what the filter and search let through.
   *
   * @param {{ all: any[], flights: any[], loading: boolean, loadError: boolean, selected: string | null }} state
   */
  function render(state) {
    if (state.loadError) {
      clearRows();
      showNotice("list-error");

      return;
    }

    if (!state.loading && state.all.length === 0) {
      clearRows();
      showNotice("empty");

      return;
    }

    if (!state.loading && state.flights.length === 0) {
      clearRows();
      showNotice("no-match");

      return;
    }

    clearNotice();

    if (!listbox) {
      listbox = document.createElement("div");
      listbox.setAttribute("role", "listbox");
      listbox.setAttribute("aria-label", "Live flights");
      listbox.className = "grid gap-0.5";
      container.append(listbox);
    }

    const keep = new Set();

    state.flights.forEach((flight, index) => {
      keep.add(flight.pirepId);

      let row = rows.get(flight.pirepId);

      if (!row) {
        row = cloneTemplate(root, "row");
        row.addEventListener("click", () => onSelect(flight.pirepId));
        rows.set(flight.pirepId, row);
      }

      setField(row, "ident", flight.ident);
      setField(
        row,
        "route",
        `${flight.dptAirport?.icao ?? "—"} → ${flight.arrAirport?.icao ?? "—"}`,
      );
      setField(
        row,
        "meta",
        [flight.pilot?.name, flight.aircraft?.name ?? flight.aircraft?.registration]
          .filter(Boolean)
          .join(" · "),
      );
      setField(row, "phase", flight.status ?? "");
      row.querySelector('[data-field="phase"]')?.toggleAttribute("hidden", !flight.status);
      setField(row, "altitude", formatAltitude(flight.position?.altitude));

      const progress = row.querySelector('[data-field="progress"]');

      if (progress instanceof HTMLProgressElement) {
        progress.value = Math.min(Math.max(Number(flight.progress) || 0, 0), 100);
      }

      const stage = stageOf(flight);

      if (stage) {
        row.dataset.stage = stage;
      } else {
        delete row.dataset.stage;
      }

      row.setAttribute("aria-selected", String(flight.pirepId === state.selected));

      if (listbox.children[index] !== row) {
        listbox.insertBefore(row, listbox.children[index] ?? null);
      }
    });

    for (const [pirepId, row] of rows) {
      if (!keep.has(pirepId)) {
        row.remove();
        rows.delete(pirepId);
      }
    }
  }

  /** @param {string} name */
  function showNotice(name) {
    if (notice?.dataset.notice === name) {
      return;
    }

    clearNotice();
    notice = cloneTemplate(root, name);
    notice.dataset.notice = name;
    container.append(notice);
  }

  return { render };
}
