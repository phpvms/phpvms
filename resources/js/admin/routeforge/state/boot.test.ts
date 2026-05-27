/**
 * Vitest coverage for the boot envelope store contract:
 *   - getBootOrThrow() throws when no hydration has occurred yet
 *   - hydrateBoot() seeds the envelope and clears any prior error
 */

import { afterEach, describe, expect, it } from "vitest";

import { bootEnvelope, bootError, getBootOrThrow, hydrateBoot } from "./boot";
import type { BootEnvelope } from "./types";

afterEach(() => {
  // Module-scoped signals leak across tests; reset by re-assigning .value.
  bootEnvelope.value = null;
  bootError.value = null;
});

const sampleEnvelope: BootEnvelope = {
  csrf_token: "token-xyz",
  locale: "en",
  user: { id: 42, name: "Test Admin", can_commit: true },
  airlines: [{ id: 1, name: "Alpha", icao: "ALP", iata: "AL" }],
  routes: {
    preview_airports: "/preview",
    subfleets: "/subfleets",
    airline_stats: "/stats",
    lint: "/lint",
    commit: "/commit",
    bundles: "/bundles",
    bundle_edit_template: "/bundles/:id/edit",
  },
  config: {},
  translations: {},
};

describe("boot store", () => {
  it("throws before any hydration", () => {
    expect(() => getBootOrThrow()).toThrowError(/not hydrated/);
  });

  it("returns the envelope after hydrateBoot", () => {
    hydrateBoot(sampleEnvelope);
    expect(getBootOrThrow()).toEqual(sampleEnvelope);
  });

  it("clears any prior boot error on successful hydration", () => {
    bootError.value = "stale error";
    hydrateBoot(sampleEnvelope);
    expect(bootError.value).toBeNull();
  });
});
