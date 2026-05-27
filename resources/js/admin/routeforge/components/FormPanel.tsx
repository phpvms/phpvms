/**
 * Left-pane composition.
 *
 * Each top-level widget (TopologyPicker, PresetPicker, etc.) imports the
 * `form` signal directly and mutates via immutable splat — there is no prop
 * drilling. FormPanel's only job is layout + composition + the two simple
 * inline <select>s for Airline and Flight type that didn't warrant their
 * own files (one-line <select>s with pre-loaded option lists).
 *
 * Event picker is intentionally deferred for v1: `/admin/route-forge/api`
 * has no events endpoint, and the /boot envelope does not expose an
 * events list. Server-side L8 still gates commits with event-date checks
 * when an event_id is set out-of-band (e.g., via a v2 add-on). The
 * placeholder below makes the deferral explicit in the UI.
 *
 * Section ordering matches the form's logical flow: topology shapes input
 * multiplicity → presets prefill → airline gates subfleets → airports →
 * subfleets + flight type → days + time → flight numbers → bundle config.
 */

import type { ComponentChildren } from "preact";

import { AirportPicker } from "./AirportPicker";
// "tour" is the internal Topology discriminator; the user-facing label is
// "Tour" (resources/lang/en/filament.php → topology_options.tour). Tour mode
// builds rows as sequential origins[i]→origins[i+1] and ignores destinations.
const TOUR_TOPOLOGY = "tour";
import { BundleConfigSection } from "./BundleConfigSection";
import { DaysPicker } from "./DaysPicker";
import { Field, INPUT_CLASS } from "./Field";
import { FlightNumberStrategyPicker } from "./FlightNumberStrategyPicker";
import { PresetPicker } from "./PresetPicker";
import { ReturnFlightsToggle } from "./ReturnFlightsToggle";
import { SubfleetPicker } from "./SubfleetPicker";
import { TimeStrategyControls } from "./TimeStrategyControls";
import { TopologyPicker } from "./TopologyPicker";
import { t } from "../lib/i18n";
import { getBootOrThrow } from "../state/boot";
import { form } from "../state/store";
import type { FlightTypeCode } from "../state/types";

export function FormPanel() {
  const f = form.value;
  const destinationsDisabled = f.topology === TOUR_TOPOLOGY;

  return (
    <div class="space-y-6">
      <SectionShell title="Topology & presets">
        <TopologyPicker />
        <PresetPicker kind="route" />
      </SectionShell>

      <SectionShell title="Bundle">
        <BundleConfigSection />
      </SectionShell>

      <SectionShell title="Airline & subfleets">
        <AirlineSelect />
        <SubfleetPicker />
        <FlightTypeSelect />
        <EventPlaceholder />
      </SectionShell>

      <SectionShell title="Airports">
        <AirportPicker mode="origin" />
        <AirportPicker
          mode="destination"
          disabled={destinationsDisabled}
          hint={
            destinationsDisabled
              ? "Not used for Tour topology — rows traverse Origins sequentially (A→B→C)."
              : undefined
          }
        />
        <ReturnFlightsToggle />
      </SectionShell>

      <SectionShell title="Schedule">
        <PresetPicker kind="frequency" />
        <DaysPicker />
        <TimeStrategyControls />
      </SectionShell>

      <SectionShell title="Flight numbers">
        <FlightNumberStrategyPicker />
      </SectionShell>
    </div>
  );
}

function SectionShell({ title, children }: { title: string; children: ComponentChildren }) {
  return (
    <section class="rounded border border-gray-200 p-4 dark:border-gray-700">
      <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {title}
      </h3>
      {children}
    </section>
  );
}

function AirlineSelect() {
  const f = form.value;
  const airlines = getBootOrThrow().airlines;

  function handleChange(e: Event): void {
    const raw = (e.currentTarget as HTMLSelectElement).value;
    let next: number | null = null;
    if (raw !== "") {
      const parsed = Number.parseInt(raw, 10);
      next = Number.isNaN(parsed) ? null : parsed;
    }
    // Changing airline invalidates the prior subfleet selection (subfleets
    // are airline-scoped). SubfleetPicker's useEffect will refetch.
    form.value = {
      ...f,
      airline_id: next,
      subfleet_ids: [],
    };
  }

  return (
    <Field
      label={t("form.airline")}
      htmlFor="rf-airline"
      hint="Scopes the subfleet list and stamps every generated flight."
      required
    >
      <select
        id="rf-airline"
        class={INPUT_CLASS}
        value={f.airline_id === null ? "" : String(f.airline_id)}
        onChange={handleChange}
      >
        <option value="">{t("airline_picker.placeholder")}</option>
        {airlines.map((a) => (
          <option key={a.id} value={a.id}>
            {a.icao !== null && a.icao !== "" ? `${a.icao} · ` : ""}
            {a.name}
          </option>
        ))}
      </select>
    </Field>
  );
}

function FlightTypeSelect() {
  const f = form.value;
  // Server pre-resolves FlightType::cases() → IATA → localized label and
  // ships the map under `translations.flight_types`. We iterate the map's
  // own keys (server emits them in enum declaration order via
  // `foreach (FlightType::cases())`) so the option ordering stays stable
  // and matches admin Filament selects elsewhere.
  const flightTypeMap =
    (getBootOrThrow().translations as { flight_types?: Record<string, string> }).flight_types ?? {};
  const flightTypeCodes = Object.keys(flightTypeMap) as FlightTypeCode[];

  function handleChange(e: Event): void {
    const raw = (e.currentTarget as HTMLSelectElement).value;
    form.value = {
      ...f,
      flight_type: raw === "" ? null : (raw as FlightTypeCode),
    };
  }

  return (
    <Field
      label={t("form.flight_type")}
      htmlFor="rf-flight-type"
      hint="Single batch-wide service type (24-case enum). Route presets pre-fill."
    >
      <select
        id="rf-flight-type"
        class={INPUT_CLASS}
        value={f.flight_type ?? ""}
        onChange={handleChange}
      >
        <option value="">— None —</option>
        {flightTypeCodes.map((code) => (
          <option key={code} value={code}>
            {code} — {t(`flight_types.${code}`)}
          </option>
        ))}
      </select>
    </Field>
  );
}

function EventPlaceholder() {
  return (
    <Field label={t("form.event")} hint="Event picker deferred to v2 (no /events endpoint yet).">
      <select class={INPUT_CLASS} disabled>
        <option>— Events backend pending —</option>
      </select>
    </Field>
  );
}
