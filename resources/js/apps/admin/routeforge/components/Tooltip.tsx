/**
 * Hover/focus tooltip matching Filament's visual style.
 *
 * Filament's tooltips are Tippy.js + Alpine.js — those don't reach into our
 * Preact island. This is a tiny stand-in that ships zero new dependencies
 * and ~40 LOC: dark rounded popover, downward arrow, ~200ms hover delay,
 * keyboard-focusable, Escape dismisses.
 *
 * Positioning is CSS-only (bottom-full + transform) so it sits above the
 * trigger. No collision detection — if the trigger is near the viewport
 * top the popover may clip. Acceptable for v1; swap to floating-ui later
 * if real cases surface.
 *
 * Usage:
 *
 *   <Tooltip text="Helpful explanation">
 *       <button aria-label="help">?</button>
 *   </Tooltip>
 *
 * Children should be focusable so keyboard users can trigger the tooltip
 * via Tab → focus event bubbles up to the wrapper.
 */

import type { ComponentChildren } from "preact";
import { useEffect, useRef, useState } from "preact/hooks";

export type TooltipProps = {
  text: string;
  children: ComponentChildren;
};

const SHOW_DELAY_MS = 200;
const HIDE_DELAY_MS = 100;

export function Tooltip({ text, children }: TooltipProps) {
  const [open, setOpen] = useState<boolean>(false);
  const timerRef = useRef<number | null>(null);

  function clearTimer(): void {
    if (timerRef.current !== null) {
      window.clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }

  function showWithDelay(): void {
    clearTimer();
    timerRef.current = window.setTimeout(() => setOpen(true), SHOW_DELAY_MS);
  }

  function hideWithDelay(): void {
    clearTimer();
    timerRef.current = window.setTimeout(() => setOpen(false), HIDE_DELAY_MS);
  }

  function showImmediate(): void {
    clearTimer();
    setOpen(true);
  }

  function hideImmediate(): void {
    clearTimer();
    setOpen(false);
  }

  useEffect(() => () => clearTimer(), []);

  return (
    <span
      class="relative inline-flex"
      onMouseEnter={showWithDelay}
      onMouseLeave={hideWithDelay}
      onFocus={showImmediate}
      onBlur={hideImmediate}
      onKeyDown={(e) => {
        if (e.key === "Escape" && open) {
          e.stopPropagation();
          setOpen(false);
        }
      }}
    >
      {children}
      {open && (
        <span
          role="tooltip"
          class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-max max-w-xs -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-xs leading-snug text-white shadow-lg dark:bg-gray-700"
        >
          {text}
          <span
            aria-hidden="true"
            class="absolute left-1/2 top-full h-0 w-0 -translate-x-1/2 border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"
          />
        </span>
      )}
    </span>
  );
}
