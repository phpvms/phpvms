/**
 * Visible "Create return flights" checkbox.
 *
 * Was previously sourced silently from the topology selection
 * (`hub_and_spokes` → true, `tour` → false, others → undefined user choice).
 * Now lives next to the airport pickers — it's a property of the airport
 * graph, not the topology dropdown.
 *
 * The checkbox stays disabled with an explanatory line when the topology
 * forces a value (`hub_and_spokes` always with returns; `tour` never),
 * so the user understands why it's not editable. For the three free
 * topologies (`hub_spokes`, `spokes_hub`, `mesh`) the user toggles freely.
 */

import { useEffect } from "preact/hooks";

import { form } from "../state/store";

export function ReturnFlightsToggle() {
  const f = form.value;

  const enforcedReturns =
    f.topology === "hub_and_spokes" ? true : f.topology === "tour" ? false : null;
  const returnsForced = enforcedReturns !== null;
  const returnsLockedReason =
    f.topology === "hub_and_spokes"
      ? "Hub & Spokes always includes return legs."
      : f.topology === "tour"
        ? "Tour mode does not produce return legs."
        : null;
  const displayChecked = enforcedReturns ?? f.create_returns;

  // Keep form.value.create_returns in lockstep with the enforced value for
  // forced topologies. TopologyPicker.deriveCreateReturns already stamps the
  // right value on topology change, but a stale draft resume can land here
  // with a mismatch (older drafts persisted `create_returns: true` while the
  // user was on tour). Without this effect a stale draft would commit a value
  // that contradicts the disabled checkbox.
  useEffect(() => {
    if (enforcedReturns === null || f.create_returns === enforcedReturns) {
      return;
    }
    form.value = { ...form.value, create_returns: enforcedReturns };
  }, [enforcedReturns, f.create_returns]);

  function handleChange(e: Event): void {
    const checkbox = (e.currentTarget as HTMLInputElement).checked;
    // Honor the forced value even if the disabled attribute is bypassed (a
    // user with devtools, an automation tool, or a future a11y workflow).
    form.value = {
      ...form.value,
      create_returns: enforcedReturns ?? checkbox,
    };
  }

  return (
    <div class="mb-3">
      <label
        class={`inline-flex items-center gap-2 text-sm ${
          returnsForced
            ? "cursor-not-allowed text-gray-500 dark:text-gray-400"
            : "cursor-pointer text-gray-700 dark:text-gray-300"
        }`}
      >
        <input
          type="checkbox"
          class="h-4 w-4 disabled:cursor-not-allowed"
          checked={displayChecked}
          disabled={returnsForced}
          onChange={handleChange}
        />
        <span class="font-medium">Create return flights</span>
      </label>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        {returnsLockedReason ?? "Auto-paired outbound + return legs for every route."}
      </p>
    </div>
  );
}
