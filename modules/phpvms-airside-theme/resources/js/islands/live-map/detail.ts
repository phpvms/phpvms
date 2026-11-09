import { cloneTemplate, formatAltitude, setField } from "./dom";
import type { FlightDetail, LiveFlight } from "./types";

const STAGE_ORDER = ["ground", "departing", "enroute", "arriving"];

/**
 * An ISO timestamp as HH:MMZ, or an em dash.
 */
function utcTime(iso: string | null | undefined): string {
  const date = iso ? new Date(iso) : null;

  if (!date || Number.isNaN(date.getTime())) {
    return "—";
  }

  return `${String(date.getUTCHours()).padStart(2, "0")}:${String(date.getUTCMinutes()).padStart(2, "0")}Z`;
}

/**
 * Minutes as "6h 45m", or an em dash.
 */
function duration(minutes: number | null | undefined): string {
  const value = Number(minutes);

  if (!Number.isFinite(value) || value <= 0) {
    return "—";
  }

  return `${Math.floor(value / 60)}h ${String(Math.round(value % 60)).padStart(2, "0")}m`;
}

/**
 * A cruise level as filed: feet (35000) or hundreds of feet (350), shown as FL350.
 */
function cruiseLevel(level: number | null | undefined): string {
  const value = Number(level);

  if (!Number.isFinite(value) || value <= 0) {
    return "—";
  }

  return `FL${String(value >= 1000 ? Math.round(value / 100) : value).padStart(3, "0")}`;
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");
}

/**
 * The selected-flight panel. The page's template (`detailed`) shows the route, progress,
 * stage, live altitude and heading, times and pilot, with a close button; the widget's
 * compact template shows the callsign and route as a link. Fields a template does not
 * have are skipped.
 *
 * `root` is the island root and `container` the overlay element.
 */
export function createDetailOverlay(
  root: HTMLElement,
  container: HTMLElement,
  onClose: () => void,
  stageOfPhase: (phase: string) => string | null,
) {
  let card: HTMLElement | null = null;

  /**
   * `live` is the selected flight's row from the last poll (position and progress).
   */
  function render(state: {
    selected: string | null;
    detail: FlightDetail | null;
    live: LiveFlight | null;
    detailLoading: boolean;
    detailError: boolean;
  }): void {
    if (!state.selected) {
      card?.remove();
      card = null;

      return;
    }

    if (!card) {
      card = cloneTemplate(root, "detail");
      card.querySelector('[data-action="close"]')?.addEventListener("click", onClose);
      container.append(card);
    }

    const status = card.querySelector('[data-field="status"]');
    const error = card.querySelector('[data-field="error"]');
    const link = card.querySelector('[data-field="link"]');
    const body = card.querySelector('[data-field="body"]') ?? link;

    if (status instanceof HTMLElement) {
      status.hidden = !state.detailLoading;
      status.textContent = "Loading…";
    }

    if (error instanceof HTMLElement) {
      error.hidden = state.detailLoading || !state.detailError;
    }

    if (body instanceof HTMLElement) {
      body.hidden = state.detailLoading || state.detailError || !state.detail;
    }

    const detail = state.detail;

    if (!detail) {
      return;
    }

    const live = state.live;
    const airline = detail.airline;

    setField(card, "callsign", detail.callsign ?? detail.ident ?? "");
    setField(card, "airline", [airline?.name, detail.callsign ? detail.ident : null].filter(Boolean).join(" · "));
    setField(card, "dpt", detail.airports?.dpt?.icao ?? "—");
    setField(card, "dpt-name", detail.airports?.dpt?.name ?? "");
    setField(card, "arr", detail.airports?.arr?.icao ?? "—");
    setField(card, "arr-name", detail.airports?.arr?.name ?? "");
    setField(card, "off", `Off ${utcTime(detail.blockOffTime)}`);
    setField(card, "eta", `Sched ${utcTime(detail.scheduledArrivalAt)}`);
    setField(
      card,
      "flown",
      detail.distance && detail.plannedDistance
        ? `${detail.distance} of ${detail.plannedDistance}`
        : String(detail.distance ?? ""),
    );
    setField(card, "phase", detail.status ?? "—");
    setField(card, "altitude", formatAltitude(live?.position?.altitude));
    setField(
      card,
      "heading",
      Number.isFinite(Number(live?.position?.heading))
        ? `${String(Math.round(Number(live?.position.heading)) % 360).padStart(3, "0")}°`
        : "—",
    );
    setField(card, "cruise", cruiseLevel(detail.cruiseLevel));
    setField(card, "flight-time", duration(detail.flightTime));
    setField(card, "planned-time", duration(detail.plannedFlightTime));
    setField(card, "pilot", detail.pilot?.name ?? "—");
    setField(card, "avatar", detail.pilot ? initials(detail.pilot.name) : "");
    setField(
      card,
      "aircraft",
      [detail.pilot?.ident, detail.aircraft?.name, detail.aircraft?.registration].filter(Boolean).join(" · "),
    );

    const logo = card.querySelector('[data-field="logo"]');

    if (logo instanceof HTMLImageElement) {
      logo.hidden = !airline?.logo;

      if (airline?.logo && logo.getAttribute("src") !== airline.logo) {
        logo.src = airline.logo;
      }
    }

    setField(card, "logo-text", airline?.logo ? "" : (airline?.icao ?? "").slice(0, 3));

    const progress = card.querySelector('[data-field="progress"]');

    if (progress instanceof HTMLProgressElement) {
      progress.value = Math.min(Math.max(Number(live?.progress) || 0, 0), 100);
    }

    // Stages before the current one are done, the current one is current.
    const current = STAGE_ORDER.indexOf(detail.phase ? (stageOfPhase(detail.phase) ?? "") : "");

    card.querySelectorAll("[data-stage]").forEach((el) => {
      if (!(el instanceof HTMLElement)) {
        return;
      }

      const index = STAGE_ORDER.indexOf(el.dataset.stage ?? "");

      el.dataset.state = current < 0 ? "todo" : index < current ? "done" : index === current ? "current" : "todo";
    });

    if (link instanceof HTMLAnchorElement) {
      link.href = `/pireps/${encodeURIComponent(detail.pirepId)}`;
    }
  }

  return { render };
}
