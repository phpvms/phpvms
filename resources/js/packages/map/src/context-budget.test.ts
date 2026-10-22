import { describe, expect, it } from "vitest";
import {
  activeContextCount,
  canCreateNewContext,
  existingMapFor,
  registerMap,
  releaseContextSlot,
  reserveContextSlot,
  unregisterMap,
} from "./context-budget.ts";

// Module-scope state (by design — see the file's own doc comment) means each test must
// `unregisterMap` everything it registers itself, or its registrations leak into later tests.

function fakeMap(): unknown {
  return {};
}

describe("context-budget", () => {
  it("reports no existing map for an element never registered", () => {
    expect(existingMapFor(document.createElement("div"))).toBeUndefined();
  });

  it("returns the registered map for its own element, and nothing for a different one", () => {
    const el = document.createElement("div");
    const other = document.createElement("div");
    const map = fakeMap();
    registerMap(el, map as never);

    expect(existingMapFor(el)).toBe(map);
    expect(existingMapFor(other)).toBeUndefined();

    unregisterMap(el);
  });

  it("unregistering stops accounting for it", () => {
    const el = document.createElement("div");
    registerMap(el, fakeMap() as never);
    unregisterMap(el);
    expect(existingMapFor(el)).toBeUndefined();
  });

  it("allows new contexts until the budget is reached, then refuses", () => {
    const els = Array.from({ length: 10 }, () => document.createElement("div"));
    try {
      let created = 0;
      for (const el of els) {
        if (!canCreateNewContext()) break;
        registerMap(el, fakeMap() as never);
        created++;
      }
      expect(created).toBeGreaterThan(0);
      expect(canCreateNewContext()).toBe(false);
      expect(activeContextCount()).toBe(created);
    } finally {
      for (const el of els) unregisterMap(el);
    }
  });

  it("refcounts a second registration for the SAME element rather than treating it as independent", () => {
    // Regression for a real bug (codex review, verified): `createMap`'s reuse path used to hand
    // out a second, independent `destroy()` closure for an already-live map with no bookkeeping
    // tying the two together — either owner's `unregisterMap` immediately freed the slot (and, in
    // `base-map.ts`, tore down the shared map) out from under the other.
    const el = document.createElement("div");
    const map = fakeMap();
    registerMap(el, map as never); // first owner
    registerMap(el, map as never); // second owner (the reuse path)
    expect(activeContextCount()).toBe(1); // one distinct element, not two

    expect(unregisterMap(el)).toBe(false); // first owner releases — second still holds it
    expect(existingMapFor(el)).toBe(map); // still registered

    expect(unregisterMap(el)).toBe(true); // second (last) owner releases
    expect(existingMapFor(el)).toBeUndefined();
  });

  it("counts a pending reservation against the budget before it becomes a real registration", () => {
    // Regression for a real bug (codex review, verified): `canCreateNewContext()` used to be
    // checked, then an `await` happened, then `registerMap` ran — a second concurrent caller for
    // a DIFFERENT element could observe room under the budget during that window and also
    // proceed, together exceeding `MAX_CONTEXTS`. `reserveContextSlot` closes it by reserving a
    // slot SYNCHRONOUSLY, before any `await`.
    const els = Array.from({ length: 10 }, () => document.createElement("div"));
    const reserved: HTMLElement[] = [];
    try {
      let count = 0;
      for (const el of els) {
        if (!canCreateNewContext()) break;
        reserveContextSlot(el);
        reserved.push(el);
        count++;
      }
      expect(count).toBeGreaterThan(0);
      expect(canCreateNewContext()).toBe(false); // reservations alone exhaust the budget
      expect(activeContextCount()).toBe(0); // none of them are REAL registrations yet
    } finally {
      for (const el of reserved) releaseContextSlot(el);
    }
    expect(canCreateNewContext()).toBe(true); // releasing them frees the budget back up
  });
});
