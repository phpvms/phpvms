/**
 * Bundle config section — dual-mode picker.
 *
 * The user either:
 *   - **Picks an existing FlightBundle** from the typeahead combobox →
 *     `bundle.existing_bundle_id` is set; the section renders a read-only
 *     summary (name / description / dates / enabled) of the bundle being
 *     attached to. fare_multiplier input is hidden in this mode (it doesn't
 *     apply to an existing bundle in the v1 UI — power-user override is
 *     deferred to v2). A "Change" affordance clears the selection.
 *
 *   - **Types a new name** in the combobox → `existing_bundle_id` stays
 *     null, the typed text drives `bundle.name`, and the full config form
 *     renders (description, dates, enabled toggle, fare multiplier). On
 *     commit the server creates a fresh FlightBundle row from those values.
 *
 * The picker reads `window.routeforgeConfig.bundles` (a server-injected
 * array of bundle summaries, populated at Filament page mount). All
 * filtering is in-memory — fine at typical VA scale (dozens to low hundreds
 * of bundles). No /admin/route-forge/api/bundles endpoint exists in v1.
 *
 * Selecting a bundle from the dropdown also pre-fills the new-mode fields
 * (description, dates, enabled) from the picked bundle's values, so if the
 * user clicks Change and falls back to "create new" mode the form is
 * already populated with a useful starting point.
 */

import { useEffect, useState } from "preact/hooks";

import { t } from "../lib/i18n";
import { form } from "../state/store";
import type { BundleConfig, BundleSummary } from "../state/types";
import { Field, INPUT_CLASS, INPUT_CLASS_ERROR } from "./Field";

const FARE_MULTIPLIER_RE = /^[+-]?\d+(\.\d+)?%$/;
const PICKER_INPUT_ID = "rf-bundle-picker";
const MAX_RESULTS = 10;
const BLUR_CLOSE_DELAY_MS = 150;

function validateFareMultiplier(value: string): string | null {
  if (value === "") {
    return null;
  }
  if (!FARE_MULTIPLIER_RE.test(value)) {
    return 'Must look like "+10%", "-5%", or "20%". Empty = no multiplier.';
  }
  return null;
}

export function BundleConfigSection() {
  const f = form.value;
  const b = f.bundle;

  function patch(updates: Partial<typeof b>): void {
    form.value = { ...f, bundle: { ...b, ...updates } };
  }

  return (
    <div>
      <BundlePicker
        bundleName={b.name}
        existingId={b.existing_bundle_id}
        onSelectExisting={(bundle) => {
          // Pre-fill the new-mode fields so falling back via "Change"
          // produces a useful starting point, not a blank form.
          patch({
            existing_bundle_id: bundle.id,
            name: bundle.name,
            description: bundle.description ?? "",
            enabled: bundle.enabled,
            activate_on_save: bundle.enabled,
            start_date: bundle.start_date,
            end_date: bundle.end_date,
          });
        }}
        onTypeNewName={(name) => {
          // Free-text typing always clears existing selection.
          patch({ existing_bundle_id: null, name });
        }}
        onClearSelection={() => {
          patch({ existing_bundle_id: null });
        }}
      />

      {b.existing_bundle_id === null ? <NewBundleFields b={b} patch={patch} /> : null}
    </div>
  );
}

// ─── Picker ───────────────────────────────────────────────────────────────

type BundlePickerProps = {
  bundleName: string;
  existingId: number | null;
  onSelectExisting: (bundle: BundleSummary) => void;
  onTypeNewName: (name: string) => void;
  onClearSelection: () => void;
};

function BundlePicker({
  bundleName,
  existingId,
  onSelectExisting,
  onTypeNewName,
  onClearSelection,
}: BundlePickerProps) {
  const bundles = window.routeforgeConfig?.bundles ?? [];
  const [query, setQuery] = useState<string>(existingId === null ? bundleName : "");
  const [open, setOpen] = useState<boolean>(false);
  // Tracks the typed name the user pressed Enter on. Suppresses the
  // "Will create a new bundle: X" hint once acknowledged; typing more
  // (which mutates query) makes query !== confirmedNewName again, so the
  // hint comes back.
  const [confirmedNewName, setConfirmedNewName] = useState<string | null>(null);

  // Resume-from-draft: when the form signal swaps under us, sync the
  // local query so the visible text matches the underlying state.
  useEffect(() => {
    if (existingId === null) {
      setQuery(bundleName);
    }
  }, [existingId, bundleName]);

  // Existing-bundle read-only branch
  if (existingId !== null) {
    const selected = bundles.find((x) => x.id === existingId) ?? null;
    return (
      <ExistingBundleSummary
        bundle={selected}
        fallbackName={bundleName}
        onChange={() => {
          onClearSelection();
          setQuery(bundleName);
          setOpen(true);
        }}
      />
    );
  }

  const q = query.trim().toLowerCase();
  const filtered =
    q === ""
      ? bundles.slice(0, MAX_RESULTS)
      : bundles.filter((bundle) => bundle.name.toLowerCase().includes(q)).slice(0, MAX_RESULTS);
  const hasExactMatch = bundles.some((bundle) => bundle.name.toLowerCase() === q);
  const showCreateHint =
    query.trim() !== "" && !hasExactMatch && query.trim() !== (confirmedNewName ?? "");

  return (
    <Field
      label={t("bundle.picker_label")}
      htmlFor={PICKER_INPUT_ID}
      hint={t("bundle.picker_hint")}
      required
      tooltip="Pick a Flight Bundle from the dropdown to append these new flights to it (description / dates / enabled flag come from the existing bundle). Or type a fresh name to create a new bundle from this batch."
    >
      <div class="relative">
        <input
          id={PICKER_INPUT_ID}
          type="text"
          autocomplete="off"
          placeholder={t("bundle.picker_placeholder")}
          class={INPUT_CLASS}
          value={query}
          onInput={(e) => {
            const v = (e.currentTarget as HTMLInputElement).value;
            setQuery(v);
            onTypeNewName(v);
            setOpen(true);
            // Typing past the confirmed name re-arms the hint.
            if (v.trim() !== (confirmedNewName ?? "")) {
              setConfirmedNewName(null);
            }
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => {
            // Defer so an item mousedown registers before close.
            setTimeout(() => setOpen(false), BLUR_CLOSE_DELAY_MS);
          }}
          onKeyDown={(e) => {
            // Enter confirms the typed name as a new bundle,
            // closes the dropdown, and dismisses the persistent
            // "Will create a new bundle: X" hint below the field.
            // Matches the dropdown's "Press Enter to use this as
            // a new bundle name" instruction. Escape closes the
            // dropdown without confirming.
            if (e.key === "Enter") {
              e.preventDefault();
              const trimmed = query.trim();
              if (trimmed !== "") {
                setConfirmedNewName(trimmed);
              }
              setOpen(false);
              (e.currentTarget as HTMLInputElement).blur();
            } else if (e.key === "Escape") {
              setOpen(false);
            }
          }}
        />
        {open && (
          <div class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
            {filtered.length === 0 && query.trim() !== "" && (
              <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                {t("bundle.no_matches")}
              </div>
            )}
            {filtered.map((bundle) => (
              <button
                key={bundle.id}
                type="button"
                class="block w-full px-3 py-1.5 text-left text-sm hover:bg-primary-50 dark:hover:bg-primary-900/30"
                onMouseDown={(e) => {
                  e.preventDefault();
                  onSelectExisting(bundle);
                  setQuery(bundle.name);
                  setOpen(false);
                }}
              >
                <div class="font-medium text-gray-900 dark:text-gray-100">{bundle.name}</div>
                {(bundle.start_date !== null || bundle.end_date !== null) && (
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    {bundle.start_date ?? "…"} → {bundle.end_date ?? "…"}
                  </div>
                )}
              </button>
            ))}
          </div>
        )}
      </div>
      {showCreateHint && (
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          {t("bundle.create_new_hint", { name: query.trim() })}
        </p>
      )}
    </Field>
  );
}

// ─── Existing-bundle read-only summary ─────────────────────────────────────

type ExistingBundleSummaryProps = {
  bundle: BundleSummary | null;
  fallbackName: string;
  onChange: () => void;
};

function ExistingBundleSummary({ bundle, fallbackName, onChange }: ExistingBundleSummaryProps) {
  const empty = t("bundle.value_empty");
  const yes = t("bundle.value_yes");
  const no = t("bundle.value_no");

  // bundle === null path: the picked id is no longer in the page's bundles
  // list (e.g. someone deleted it between page load and resume). Show the
  // last-known name and a Change affordance.
  const name = bundle?.name ?? fallbackName;
  const description = bundle?.description ?? null;
  const startDate = bundle?.start_date ?? null;
  const endDate = bundle?.end_date ?? null;
  const enabled = bundle?.enabled ?? false;

  return (
    <Field label={t("bundle.picker_label")}>
      <div class="rounded border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
        <div class="mb-2 flex items-start justify-between gap-2">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
              {t("bundle.existing_label")}
            </p>
            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{name}</p>
          </div>
          <button
            type="button"
            class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
            onClick={onChange}
          >
            {t("bundle.change_selection")}
          </button>
        </div>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
          <dt class="font-medium text-gray-500 dark:text-gray-400">
            {t("bundle.field_description")}
          </dt>
          <dd class="text-gray-700 dark:text-gray-200">
            {description !== null && description !== "" ? description : empty}
          </dd>
          <dt class="font-medium text-gray-500 dark:text-gray-400">
            {t("bundle.field_start_date")}
          </dt>
          <dd class="font-mono text-gray-700 dark:text-gray-200">{startDate ?? empty}</dd>
          <dt class="font-medium text-gray-500 dark:text-gray-400">{t("bundle.field_end_date")}</dt>
          <dd class="font-mono text-gray-700 dark:text-gray-200">{endDate ?? empty}</dd>
          <dt class="font-medium text-gray-500 dark:text-gray-400">{t("bundle.field_enabled")}</dt>
          <dd class="text-gray-700 dark:text-gray-200">{enabled ? yes : no}</dd>
        </dl>
      </div>
    </Field>
  );
}

// ─── New-bundle fields ────────────────────────────────────────────────────

type NewBundleFieldsProps = {
  b: BundleConfig;
  patch: (updates: Partial<BundleConfig>) => void;
};

function NewBundleFields({ b, patch }: NewBundleFieldsProps) {
  function changeDescription(e: Event): void {
    patch({ description: (e.currentTarget as HTMLTextAreaElement).value });
  }
  function changeEnabled(e: Event): void {
    const enabled = (e.currentTarget as HTMLInputElement).checked;
    // Keep activate_on_save in sync with enabled — they are two UI sites
    // for the same underlying intent.
    patch({ enabled, activate_on_save: enabled });
  }
  function changeStartDate(e: Event): void {
    const value = (e.currentTarget as HTMLInputElement).value;
    patch({ start_date: value === "" ? null : value });
  }
  function changeEndDate(e: Event): void {
    const value = (e.currentTarget as HTMLInputElement).value;
    patch({ end_date: value === "" ? null : value });
  }
  function changeFareMultiplier(e: Event): void {
    patch({ fare_multiplier: (e.currentTarget as HTMLInputElement).value });
  }

  const fareError = validateFareMultiplier(b.fare_multiplier);

  return (
    <>
      <Field label="Description" htmlFor="rf-bundle-desc" hint="Optional. Plain text.">
        <textarea
          id="rf-bundle-desc"
          class={INPUT_CLASS}
          rows={2}
          value={b.description}
          onInput={changeDescription}
        />
      </Field>

      <div class="mb-3">
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input type="checkbox" class="h-4 w-4" checked={b.enabled} onChange={changeEnabled} />
          <span class="font-medium">Activate this Flight Bundle on save</span>
        </label>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          When checked, flights are visible to pilots immediately after commit. Leave unchecked to
          commit as a draft bundle.
        </p>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <Field label="Start date" htmlFor="rf-bundle-start" hint="Optional window start.">
          <input
            id="rf-bundle-start"
            type="date"
            class={INPUT_CLASS}
            value={b.start_date ?? ""}
            onInput={changeStartDate}
          />
        </Field>
        <Field label="End date" htmlFor="rf-bundle-end" hint="Optional window end.">
          <input
            id="rf-bundle-end"
            type="date"
            class={INPUT_CLASS}
            value={b.end_date ?? ""}
            onInput={changeEndDate}
          />
        </Field>
      </div>

      <Field
        label="Fare adjustment"
        htmlFor="rf-bundle-fare"
        hint='e.g. "+10%", "-5%", "20%". Empty = inherit subfleet fares unchanged. Affects price only.'
        error={fareError}
      >
        <input
          id="rf-bundle-fare"
          type="text"
          placeholder="+10%"
          class={fareError !== null ? INPUT_CLASS_ERROR : INPUT_CLASS}
          value={b.fare_multiplier}
          onInput={changeFareMultiplier}
        />
      </Field>
    </>
  );
}
