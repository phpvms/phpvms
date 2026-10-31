import { cloneTemplate, setField } from "./dom.js";

/**
 * The live flights list: one row per flight, plus the empty and error states
 * (LiveFlightList). Rows are kept by pirep id between polls, so a poll does not
 * rebuild the list or lose its scroll position.
 *
 * @param {HTMLElement} root the island root
 * @param {HTMLElement} container the list element
 * @param {(pirepId: string) => void} onSelect
 */
export function createFlightList(root, container, onSelect) {
  /** @type {Map<string, HTMLElement>} */
  const rows = new Map();
  /** @type {HTMLElement | null} */
  let listbox = null;
  /** @type {HTMLElement | null} */
  let notice = null;

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
   * @param {{ flights: any[], loading: boolean, loadError: boolean, selected: string | null }} state
   */
  function render(state) {
    if (state.loadError) {
      clearRows();
      showNotice("list-error");

      return;
    }

    if (!state.loading && state.flights.length === 0) {
      clearRows();
      showNotice("empty");

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
      setField(row, "phase", flight.phase ?? "");
      row.querySelector('[data-field="phase"]')?.toggleAttribute("hidden", !flight.phase);
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
