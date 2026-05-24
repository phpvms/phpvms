/**
 * Days-of-week picker.
 *
 * Renders 7 checkboxes mapped to bits in `form.days_mask` (Mon = 1<<0 …
 * Sun = 1<<6). Toggling a checkbox flips the matching bit. Day-mask
 * conventions match phpvms's existing day storage (see lib/presets.ts for
 * the shared bit constants).
 *
 * The frequency preset above this section also writes to `days_mask`, so the
 * checkboxes naturally reflect whatever preset the user picked. The user can
 * then check/uncheck individual days, which silently leaves the frequency
 * preset value alone (no auto-flip to "custom"). That's intentional: the
 * preset name describes intent, not derived state. v2 polish item if it
 * matters.
 */

import { form } from "../state/store";

const DAYS = [
  { bit: 1 << 0, label: "Mon" },
  { bit: 1 << 1, label: "Tue" },
  { bit: 1 << 2, label: "Wed" },
  { bit: 1 << 3, label: "Thu" },
  { bit: 1 << 4, label: "Fri" },
  { bit: 1 << 5, label: "Sat" },
  { bit: 1 << 6, label: "Sun" },
] as const;

export function DaysPicker() {
  const f = form.value;
  const mask = f.days_mask;

  function toggle(bit: number): void {
    const next = (mask & bit) !== 0 ? mask & ~bit : mask | bit;
    form.value = { ...f, days_mask: next };
  }

  function selectAll(): void {
    form.value = { ...f, days_mask: 127 };
  }
  function clearAll(): void {
    form.value = { ...f, days_mask: 0 };
  }

  return (
    <div class="mb-3">
      <div class="mb-1 flex items-center justify-between">
        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Days</span>
        <div class="flex gap-2 text-xs">
          <button
            type="button"
            class="text-primary-600 hover:underline dark:text-primary-400"
            onClick={selectAll}
          >
            All
          </button>
          <span class="text-gray-300 dark:text-gray-600">·</span>
          <button
            type="button"
            class="text-primary-600 hover:underline dark:text-primary-400"
            onClick={clearAll}
          >
            None
          </button>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        {DAYS.map(({ bit, label }) => {
          const checked = (mask & bit) !== 0;
          return (
            <label
              key={bit}
              class={
                "inline-flex cursor-pointer items-center gap-1.5 rounded border px-3 py-1.5 text-sm " +
                (checked
                  ? "border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200"
                  : "border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300")
              }
            >
              <input
                type="checkbox"
                class="h-3.5 w-3.5"
                checked={checked}
                onChange={() => toggle(bit)}
              />
              <span>{label}</span>
            </label>
          );
        })}
      </div>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Bitmask {mask} (
        {mask === 127 ? "every day" : mask === 0 ? "no days selected" : `${countBits(mask)}/7 days`}
        ).
      </p>
    </div>
  );
}

function countBits(mask: number): number {
  let n = 0;
  for (let i = 0; i < 7; i++) {
    if ((mask & (1 << i)) !== 0) {
      n++;
    }
  }
  return n;
}
