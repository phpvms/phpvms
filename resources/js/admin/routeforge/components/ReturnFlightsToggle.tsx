/**
 * Visible "Create return flights" checkbox.
 *
 * Was previously sourced silently from the topology selection
 * (`hub_and_spokes` → true, `chain` → false, others → undefined user choice).
 * Now lives next to the airport pickers — it's a property of the airport
 * graph, not the topology dropdown.
 *
 * The checkbox stays disabled with an explanatory line when the topology
 * forces a value (`hub_and_spokes` always with returns; `chain` never),
 * so the user understands why it's not editable. For the three free
 * topologies (`hub_spokes`, `spokes_hub`, `mesh`) the user toggles freely.
 */

import { form } from "../state/store";

export function ReturnFlightsToggle() {
  const f = form.value;

  function handleChange(e: Event): void {
    form.value = {
      ...f,
      create_returns: (e.currentTarget as HTMLInputElement).checked,
    };
  }

  const returnsForced = f.topology === "hub_and_spokes" || f.topology === "chain";
  const returnsLockedReason =
    f.topology === "hub_and_spokes"
      ? "Hub & Spokes always includes return legs."
      : f.topology === "chain"
        ? "Chain mode does not produce return legs."
        : null;
  // For forced topologies the displayed value MUST match the enforced value
  // so the checkbox can't show stale `f.create_returns` from a different
  // topology. TopologyPicker.deriveCreateReturns also stamps the right value
  // into form state, but using the derived value here is defense-in-depth.
  const displayChecked = returnsForced ? f.topology === "hub_and_spokes" : f.create_returns;

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
