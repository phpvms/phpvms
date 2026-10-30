import { cloneTemplate, setField } from "./dom.js";

/**
 * The selected-flight overlay on the map, as skylight builds it (LiveFlightDetail):
 * a loading line, an error line, or the flight's callsign and route as a link.
 * It has no stats and no close button.
 *
 * @param {HTMLElement} root the island root
 * @param {HTMLElement} container the overlay element
 */
export function createDetailOverlay(root, container) {
  /** @type {HTMLElement | null} */
  let card = null;

  /**
   * @param {{ selected: string | null, detail: any, detailLoading: boolean, detailError: boolean }} state
   */
  function render(state) {
    if (!state.selected) {
      card?.remove();
      card = null;

      return;
    }

    if (!card) {
      card = cloneTemplate(root, "detail");
      container.append(card);
    }

    const status = card.querySelector('[data-field="status"]');
    const error = card.querySelector('[data-field="error"]');
    const link = card.querySelector('[data-field="link"]');

    if (
      !(status instanceof HTMLElement) ||
      !(error instanceof HTMLElement) ||
      !(link instanceof HTMLAnchorElement)
    ) {
      return;
    }

    status.hidden = !state.detailLoading;
    status.textContent = "Loading…";
    error.hidden = state.detailLoading || !state.detailError;
    link.hidden = state.detailLoading || state.detailError || !state.detail;

    if (state.detail) {
      setField(card, "callsign", state.detail.callsign ?? state.detail.ident ?? "");
      setField(card, "dpt", state.detail.airports?.dpt?.icao ?? "");
      setField(card, "arr", state.detail.airports?.arr?.icao ?? "");
      link.href = `/pireps/${encodeURIComponent(state.detail.pirepId)}`;
    }
  }

  return { render };
}
