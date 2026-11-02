/**
 * The `[` shortcut toggles the sidebar store, but never while the user types;
 * only the group holding the active page stays open.
 */

import { beforeEach, describe, expect, it, vi } from "vitest";

const store = { isMobile: false, isOpen: true, collapsedGroups: [], open: vi.fn(), close: vi.fn() };

window.Alpine = { store: (name) => (name === "sidebar" ? store : undefined) };

const { collapseInactiveGroups } = await import("./sidebar-shell");

const press = (target, init = {}) => {
  const event = new KeyboardEvent("keydown", {
    key: "[",
    bubbles: true,
    cancelable: true,
    ...init,
  });
  target.dispatchEvent(event);

  return event;
};

describe("sidebar shell", () => {
  beforeEach(() => {
    store.isMobile = false;
    store.isOpen = true;
    store.open.mockClear();
    store.close.mockClear();
    document.body.innerHTML = `
      <input id="field">
      <textarea id="area"></textarea>
      <div id="editor" contenteditable="true"><span id="inner"></span></div>
      <div id="plain"></div>
      <div class="fi-main-sidebar">
        <li class="fi-sidebar-group fi-active" data-group-label="Operations"></li>
        <li class="fi-sidebar-group" data-group-label="Fleet"></li>
        <li class="fi-sidebar-group" data-group-label=""></li>
      </div>`;
  });

  it("closes an open sidebar on [", () => {
    const event = press(document.getElementById("plain"));

    expect(store.close).toHaveBeenCalledTimes(1);
    expect(event.defaultPrevented).toBe(true);
  });

  it("opens a closed sidebar on [", () => {
    store.isOpen = false;
    press(document.getElementById("plain"));

    expect(store.open).toHaveBeenCalledTimes(1);
  });

  it.each(["field", "area", "editor", "inner"])("ignores [ typed into #%s", (id) => {
    const event = press(document.getElementById(id));

    expect(store.close).not.toHaveBeenCalled();
    expect(store.open).not.toHaveBeenCalled();
    expect(event.defaultPrevented).toBe(false);
  });

  it("ignores [ with a modifier and on mobile", () => {
    press(document.getElementById("plain"), { metaKey: true });
    store.isMobile = true;
    press(document.getElementById("plain"));

    expect(store.close).not.toHaveBeenCalled();
  });

  it("collapses every named group except the active one", () => {
    collapseInactiveGroups();

    expect(store.collapsedGroups).toEqual(["Fleet"]);
  });
});
