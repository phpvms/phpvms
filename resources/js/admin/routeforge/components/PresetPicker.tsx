/**
 * Preset picker — reused twice (route preset + frequency preset) via the
 * `kind` prop.
 *
 * On change, merges the preset's patch into form.value. Selecting `custom`
 * applies an empty patch (no-op). Selecting a non-custom preset OVERWRITES
 * the corresponding form fields — there is no merge-with-edit step in v1
 * (Decision 3: explicit lifecycle, simple beats clever).
 *
 * Visual selection state always reflects the form value, not internal state,
 * so a draft resume + preset list re-render stays in sync.
 */

import { useState } from "preact/hooks";

import { form } from "../state/store";
import {
  FREQUENCY_PRESET_LABELS,
  FREQUENCY_PRESET_ORDER,
  frequencyPresetPatch,
  ROUTE_PRESET_LABELS,
  ROUTE_PRESET_ORDER,
  routePresetPatch,
} from "../lib/presets";
import type { FrequencyPreset, RoutePreset } from "../state/types";
import { Field, INPUT_CLASS } from "./Field";
import { HelpModal, type HelpModalItem } from "./HelpModal";

/**
 * Per-route-preset descriptions for the help modal. Keep in sync with
 * routePresetPatch() in lib/presets.ts — these explain the effective patch.
 */
const ROUTE_PRESET_DESCRIPTIONS: Record<RoutePreset, string> = {
  custom: "Keeps your current flight type and return-leg flag untouched.",
  regional_spoke: "Short out-and-back sectors. Scheduled passenger with auto return legs.",
  long_haul_daily:
    "Long sectors flown every day. Scheduled passenger with auto return legs, all 7 days, fixed 08:00 departure.",
  weekend_leisure:
    "Leisure routes for Sat/Sun only. Scheduled passenger with auto return legs, weekends, 10:00 spread.",
  cargo_night:
    "Overnight freight operations. Cargo flight type, no return legs, redeye window starting 22:00.",
  training: "Currency and training flights. Training flight type, no return legs.",
  positioning: "Empty repositioning legs. Positioning flight type, no return legs.",
};

export type PresetPickerProps = {
  kind: "route" | "frequency";
};

export function PresetPicker({ kind }: PresetPickerProps) {
  if (kind === "route") {
    return <RoutePresetPicker />;
  }
  return <FrequencyPresetPicker />;
}

function RoutePresetPicker() {
  const f = form.value;
  const [helpOpen, setHelpOpen] = useState<boolean>(false);

  function handleChange(e: Event): void {
    const next = (e.currentTarget as HTMLSelectElement).value as RoutePreset;
    const patch = routePresetPatch(next);
    form.value = {
      ...f,
      route_preset: next,
      ...patch,
    };
  }

  const helpItems: HelpModalItem[] = ROUTE_PRESET_ORDER.map((p) => ({
    key: p,
    label: ROUTE_PRESET_LABELS[p],
    description: ROUTE_PRESET_DESCRIPTIONS[p],
  }));

  return (
    <>
      <Field
        label="Route preset"
        htmlFor="rf-route-preset"
        hint="Prefills flight type, return-leg flag, and (when the name implies it) days + time strategy."
        onHelpClick={() => setHelpOpen(true)}
        helpAriaLabel="About route presets"
      >
        <select
          id="rf-route-preset"
          class={INPUT_CLASS}
          value={f.route_preset}
          onChange={handleChange}
        >
          {ROUTE_PRESET_ORDER.map((p) => (
            <option key={p} value={p}>
              {ROUTE_PRESET_LABELS[p]}
            </option>
          ))}
        </select>
      </Field>
      <HelpModal
        open={helpOpen}
        title="Route preset"
        subtitle="Each preset prefills the flight type and return-leg flag. Pick Custom to keep your current values."
        items={helpItems}
        currentKey={f.route_preset}
        onClose={() => setHelpOpen(false)}
      />
    </>
  );
}

function FrequencyPresetPicker() {
  const f = form.value;

  function handleChange(e: Event): void {
    const next = (e.currentTarget as HTMLSelectElement).value as FrequencyPreset;
    const patch = frequencyPresetPatch(next);
    form.value = {
      ...f,
      frequency_preset: next,
      ...patch,
    };
  }

  return (
    <Field
      label="Frequency preset"
      htmlFor="rf-frequency-preset"
      hint="Prefills the day-of-week mask and time strategy."
      tooltip="Frequency presets set the operating days + time strategy in one click: Daily (all days, fixed time), Weekdays (Mon–Fri), Weekends, 3× weekly (M/W/F), Tue/Thu/Sat, Nightly weekdays, Training (always). Pick Custom to keep your current days mask and time strategy."
    >
      <select
        id="rf-frequency-preset"
        class={INPUT_CLASS}
        value={f.frequency_preset}
        onChange={handleChange}
      >
        {FREQUENCY_PRESET_ORDER.map((p) => (
          <option key={p} value={p}>
            {FREQUENCY_PRESET_LABELS[p]}
          </option>
        ))}
      </select>
    </Field>
  );
}
