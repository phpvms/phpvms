import { cloneTemplate, formatAltitude, setField } from "./dom";
import type { LiveFlight } from "./types";

/**
 * The live flights list: one row per flight, plus the empty and error states
 * (LiveFlightList). Rows are kept by pirep id between polls, so a poll does not
 * rebuild the list or lose its scroll position.
 *
 * `root` is the island root, `container` the list element, and `stageOf` gives a
 * flight's stage from the config's stage map.
 */
export function createFlightList(
  root: HTMLElement,
  container: HTMLElement,
  onSelect: (pirepId: string) => void,
  stageOf: (flight: LiveFlight) => string | null,
) {
  const rows = new Map<string, HTMLElement>();
  let listbox: HTMLElement | null = null;
  let notice: HTMLElement | null = null;

  // Start from an empty container, whatever an earlier mount left in it.
  container.replaceChildren();

  function clearNotice(): void {
    notice?.remove();
    notice = null;
  }

  function clearRows(): void {
    listbox?.remove();
    listbox = null;
    rows.clear();
  }

  /**
   * `all` is every live flight; `flights` is what the filter and search let through.
   */
  function render(state: {
    all: LiveFlight[];
    flights: LiveFlight[];
    loading: boolean;
    loadError: boolean;
    selected: string | null;
  }): void {
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

    const box = listbox;
    const keep = new Set<string>();

    state.flights.forEach((flight, index) => {
      keep.add(flight.pirepId);

      let row = rows.get(flight.pirepId);

      if (!row) {
        row = cloneTemplate(root, "row");
        row.addEventListener("click", () => onSelect(flight.pirepId));
        rows.set(flight.pirepId, row);
      }

      setField(row, "ident", flight.ident ?? "");
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

      if (box.children[index] !== row) {
        box.insertBefore(row, box.children[index] ?? null);
      }
    });

    for (const [pirepId, row] of rows) {
      if (!keep.has(pirepId)) {
        row.remove();
        rows.delete(pirepId);
      }
    }
  }

  function showNotice(name: string): void {
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
