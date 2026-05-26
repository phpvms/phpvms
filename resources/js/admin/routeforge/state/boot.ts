/**
 * RouteForge boot envelope store.
 *
 * Replaces the legacy `window.routeforgeConfig` global. The SPA fetches
 * `/admin/route-forge/api/boot` once on mount, then calls `hydrateBoot()`
 * with the response. Every other module reads bootstrap data — CSRF token,
 * locale, user info, airlines list, route URLs, server config, translations
 * — via `getBootOrThrow()` (a signal accessor that throws when called
 * before hydration).
 *
 * The "throw before hydration" contract is deliberate: it surfaces broken
 * call ordering immediately at the offending site, instead of producing
 * subtle `undefined`-driven runtime errors downstream. Production callers
 * are scheduled after `render(<App />, root)` in main.tsx so the throw is
 * unreachable in practice; it exists as a guardrail for new contributors.
 */

import { signal } from "@preact/signals";

import type { BootEnvelope } from "./types";

/** Latest boot envelope, or null before the boot fetch resolves. */
export const bootEnvelope = signal<BootEnvelope | null>(null);

/** Most recent boot-loader error message (cleared on successful hydration). */
export const bootError = signal<string | null>(null);

/**
 * Seed the in-memory store from a successful /boot response.
 * Clears any prior error so a retry-after-failure flow lands clean.
 */
export function hydrateBoot(envelope: BootEnvelope): void {
  bootEnvelope.value = envelope;
  bootError.value = null;
}

/**
 * Read the hydrated envelope or throw. Callers that hit this path before
 * boot has finished are bugs in the bootstrap ordering — surface them
 * loudly rather than papering over with optional chains.
 */
export function getBootOrThrow(): BootEnvelope {
  const value = bootEnvelope.value;
  if (value === null) {
    throw new Error(
      "RouteForge boot envelope not hydrated yet — getBootOrThrow() called before /boot resolved.",
    );
  }
  return value;
}
