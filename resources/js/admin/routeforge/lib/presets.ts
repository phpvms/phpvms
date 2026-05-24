/**
 * Route + frequency preset data.
 *
 * Each preset describes a Form patch — partial overrides that PresetPicker
 * merges into `form.value` when the user selects it. The `custom` preset is
 * the "no-op" sentinel: selecting it leaves the form alone.
 *
 * Routes prefill `flight_type` (and optionally `create_returns`). Frequencies
 * prefill `days_mask` + `time_strategy` (per Decision 1: presets prefill
 * OPTIONS only, not destinations — destination filtering = v2).
 *
 * Defaults inside the time-strategy literals match TimeStrategyControls'
 * factory defaults so a preset followed by user edits stays internally
 * consistent.
 */

import type {
  FlightTypeCode,
  Form,
  FrequencyPreset,
  RoutePreset,
  TimeStrategy,
} from "../state/types";

/** Partial form patch applied on preset selection. Custom = empty. */
export type RoutePresetPatch = Partial<Pick<Form, "flight_type" | "create_returns">>;
export type FrequencyPresetPatch = Partial<Pick<Form, "days_mask" | "time_strategy">>;

const DEFAULT_JITTER = { enabled: false, minutes: 5, seed: 1 };

// ─── Days mask helpers (Mon = 1<<0 ... Sun = 1<<6, total 127) ─────────────

const MON = 1 << 0;
const TUE = 1 << 1;
const WED = 1 << 2;
const THU = 1 << 3;
const FRI = 1 << 4;
const SAT = 1 << 5;
const SUN = 1 << 6;

export const DAYS_ALL = MON | TUE | WED | THU | FRI | SAT | SUN; // 127
export const DAYS_WEEKDAYS = MON | TUE | WED | THU | FRI; // 31
export const DAYS_WEEKENDS = SAT | SUN; // 96
export const DAYS_MWF = MON | WED | FRI; // 21
export const DAYS_TTS = TUE | THU | SAT; // 42

// ─── Route presets ────────────────────────────────────────────────────────

export const ROUTE_PRESET_LABELS: Record<RoutePreset, string> = {
  regional_spoke: "Regional spoke",
  long_haul_daily: "Long-haul daily",
  weekend_leisure: "Weekend leisure",
  cargo_night: "Cargo night",
  training: "Training routes",
  positioning: "Positioning",
  custom: "Custom",
};

export const ROUTE_PRESET_ORDER: RoutePreset[] = [
  "custom",
  "regional_spoke",
  "long_haul_daily",
  "weekend_leisure",
  "cargo_night",
  "training",
  "positioning",
];

export function routePresetPatch(preset: RoutePreset): RoutePresetPatch {
  switch (preset) {
    case "regional_spoke":
      return { flight_type: "J" as FlightTypeCode, create_returns: true };
    case "long_haul_daily":
      return { flight_type: "J" as FlightTypeCode, create_returns: true };
    case "weekend_leisure":
      return { flight_type: "J" as FlightTypeCode, create_returns: true };
    case "cargo_night":
      return { flight_type: "F" as FlightTypeCode, create_returns: false };
    case "training":
      return { flight_type: "K" as FlightTypeCode, create_returns: false };
    case "positioning":
      return { flight_type: "P" as FlightTypeCode, create_returns: false };
    case "custom":
      return {};
  }
}

// ─── Frequency presets ────────────────────────────────────────────────────

export const FREQUENCY_PRESET_LABELS: Record<FrequencyPreset, string> = {
  daily: "Daily",
  weekdays: "Weekdays",
  weekends: "Weekends",
  three_weekly: "3× weekly (M/W/F)",
  tue_thu_sat: "Tue/Thu/Sat",
  nightly_weekdays: "Nightly weekdays",
  training_always: "Training (always visible)",
  custom: "Custom",
};

export const FREQUENCY_PRESET_ORDER: FrequencyPreset[] = [
  "custom",
  "daily",
  "weekdays",
  "weekends",
  "three_weekly",
  "tue_thu_sat",
  "nightly_weekdays",
  "training_always",
];

export function frequencyPresetPatch(preset: FrequencyPreset): FrequencyPresetPatch {
  const fixed = (base_time: string): TimeStrategy => ({
    kind: "fixed",
    base_time,
    jitter: DEFAULT_JITTER,
  });
  const spread = (base_time: string, interval_minutes: number): TimeStrategy => ({
    kind: "spread",
    base_time,
    interval_minutes,
    jitter: DEFAULT_JITTER,
  });
  const redeye = (base_time: string, window_minutes: number): TimeStrategy => ({
    kind: "redeye",
    base_time,
    window_minutes,
    jitter: DEFAULT_JITTER,
  });

  switch (preset) {
    case "daily":
      return { days_mask: DAYS_ALL, time_strategy: fixed("08:00") };
    case "weekdays":
      return { days_mask: DAYS_WEEKDAYS, time_strategy: fixed("09:00") };
    case "weekends":
      return { days_mask: DAYS_WEEKENDS, time_strategy: spread("10:00", 30) };
    case "three_weekly":
      return { days_mask: DAYS_MWF, time_strategy: fixed("09:00") };
    case "tue_thu_sat":
      return { days_mask: DAYS_TTS, time_strategy: fixed("09:00") };
    case "nightly_weekdays":
      return { days_mask: DAYS_WEEKDAYS, time_strategy: redeye("22:00", 240) };
    case "training_always":
      return { days_mask: DAYS_ALL, time_strategy: spread("08:00", 60) };
    case "custom":
      return {};
  }
}
