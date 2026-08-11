/**
 * Vitest coverage for the rail flyout surviving Livewire SPA navigation.
 *
 * The module keeps `openGroup` in module scope while its listeners are
 * delegated on `document`, so the listeners outlive a `wire:navigate` body swap
 * but the node `openGroup` points at does not. These tests pin the two ways
 * that used to blow up:
 *
 *   - a navigation while a flyout is open (the reported bug),
 *   - a close timer that outlives a DOM swap with no navigate event at all
 *     (a Livewire morph), which only the `alpineDropdown()` guard catches.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

// happy-dom answers `not all` for every media query; railActive() wants a
// hovering fine pointer. innerWidth already defaults to 1024, which clears the
// module's >= 1024 check.
window.matchMedia = () => ({ matches: true });

// Live components keep their mock here. Anything else — a torn-down element, or
// one Alpine has not initialised yet — gets a bare Proxy, which is exactly what
// the real `Alpine.$data()` returns for an empty data stack: truthy, with every
// property reading as undefined.
const alpineData = new WeakMap();

window.Alpine = {
  $data: (el) => alpineData.get(el) ?? new Proxy({}, {}),
};

await import("./rail-nav");

/**
 * Render a rail sidebar and register a live Alpine mock per group.
 *
 * @param {string[]} labels
 * @returns {Array<{li: HTMLElement, root: HTMLElement, data: {open: Function, close: Function}, tippy: {hide: Function, disable: Function}}>}
 */
function buildSidebar(labels) {
  document.body.innerHTML = `
    <div class="fi-sidebar">
      <ul>
        ${labels
          .map(
            (label) => `
          <li class="fi-sidebar-group" data-group-label="${label}">
            <div class="fi-dropdown">
              <div class="fi-dropdown-trigger">
                <button class="fi-sidebar-group-dropdown-trigger-btn"></button>
              </div>
              <div class="fi-dropdown-panel">
                <a class="fi-dropdown-list-item" href="/admin/${label}" wire:navigate></a>
              </div>
            </div>
          </li>`,
          )
          .join("")}
      </ul>
    </div>`;

  return Array.from(document.querySelectorAll("li.fi-sidebar-group")).map((li) => {
    const root = li.querySelector(".fi-dropdown");
    const button = li.querySelector(".fi-sidebar-group-dropdown-trigger-btn");
    const data = { open: vi.fn(), close: vi.fn() };
    const tippy = { hide: vi.fn(), disable: vi.fn() };

    alpineData.set(root, data);
    button._tippy = tippy;

    return { li, root, data, tippy };
  });
}

/** Simulate Alpine tearing a group down, leaving an empty data stack behind. */
function destroy(group) {
  alpineData.delete(group.root);
}

const hover = (group) => group.li.dispatchEvent(new MouseEvent("mouseover", { bubbles: true }));

const unhover = (group) => group.li.dispatchEvent(new MouseEvent("mouseout", { bubbles: true }));

beforeEach(() => {
  vi.useFakeTimers();
});

afterEach(() => {
  vi.useRealTimers();
});

describe("rail nav flyouts", () => {
  it("opens a group's flyout on hover", () => {
    const [ops] = buildSidebar(["ops"]);

    hover(ops);
    vi.advanceTimersByTime(100);

    expect(ops.data.open).toHaveBeenCalledOnce();
    expect(ops.tippy.disable).toHaveBeenCalled();
  });

  it("still opens flyouts after navigating away with one open", () => {
    const [before] = buildSidebar(["ops"]);

    hover(before);
    vi.advanceTimersByTime(100);
    expect(before.data.open).toHaveBeenCalledOnce();

    // Livewire swaps the body: the open group is destroyed, then replaced by
    // fresh nodes. `openGroup` still points at the old one until the module
    // clears it.
    document.dispatchEvent(new CustomEvent("livewire:navigate"));
    destroy(before);
    const [, plan] = buildSidebar(["ops", "plan"]);

    hover(plan);
    vi.advanceTimersByTime(100);

    expect(plan.data.open).toHaveBeenCalledOnce();
    expect(plan.tippy.disable).toHaveBeenCalled();
  });

  it("suppresses the Tippy label on hover, before the flyout is due to open", () => {
    const [ops] = buildSidebar(["ops"]);

    hover(ops);

    // Tippy shows with no delay, so waiting for OPEN_DELAY would let the label
    // paint first — the flicker. Nothing has been advanced here.
    expect(ops.tippy.disable).toHaveBeenCalled();
    expect(ops.data.open).not.toHaveBeenCalled();
  });

  it("leaves closing to Filament's own dropdown on navigation", () => {
    const [ops] = buildSidebar(["ops"]);

    hover(ops);
    vi.advanceTimersByTime(100);
    expect(ops.data.open).toHaveBeenCalledOnce();

    document.dispatchEvent(new CustomEvent("livewire:navigate"));

    // A second close in the same tick orphans Alpine's pending _x_hidePromise.
    expect(ops.data.close).not.toHaveBeenCalled();
  });

  it("survives a close timer firing after the group was torn down", () => {
    const [before] = buildSidebar(["ops"]);

    hover(before);
    vi.advanceTimersByTime(100);
    unhover(before); // arms the close timer

    // A Livewire morph replaces the group without any navigate event, so the
    // pending timer is left holding a destroyed node.
    destroy(before);
    buildSidebar(["ops"]);

    expect(() => vi.advanceTimersByTime(500)).not.toThrow();
  });
});
