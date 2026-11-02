/**
 * Vitest coverage for the PIREP panel shortcuts: they click the strip's
 * controls, skip unavailable ones, and stay out of the way while typing.
 */

import { beforeEach, describe, expect, it, vi } from "vitest";

import { handlePanelKey, newTabUrl } from "./pirep-panel";

function key(k, target = document.body, extra = {}) {
  const event = new KeyboardEvent("keydown", { key: k, bubbles: true, cancelable: true, ...extra });
  Object.defineProperty(event, "target", { value: target });

  return event;
}

describe("pirep panel keys", () => {
  let next;
  let accept;
  let input;

  beforeEach(() => {
    document.body.innerHTML = `
      <div data-pirep-strip>
        <button data-pirep-nav="next"></button>
        <button data-pirep-nav="previous" disabled></button>
        <a data-pirep-open href="#"></a>
        <button data-pirep-decide="accept"></button>
      </div>
      <input id="field" />`;
    next = document.querySelector('[data-pirep-nav="next"]');
    accept = document.querySelector('[data-pirep-decide="accept"]');
    input = document.getElementById("field");
    next.click = vi.fn();
    accept.click = vi.fn();
  });

  it("clicks the control a key maps to", () => {
    expect(handlePanelKey(key("j"))).toBe(true);
    expect(next.click).toHaveBeenCalledOnce();

    expect(handlePanelKey(key("a"))).toBe(true);
    expect(accept.click).toHaveBeenCalledOnce();
  });

  it("ignores keys typed into inputs", () => {
    expect(handlePanelKey(key("j", input))).toBe(false);
    expect(next.click).not.toHaveBeenCalled();
  });

  it("skips disabled and missing controls", () => {
    expect(handlePanelKey(key("k"))).toBe(false);
    expect(handlePanelKey(key("r"))).toBe(false);
  });

  it("leaves modified keys to the browser", () => {
    expect(handlePanelKey(key("j", document.body, { metaKey: true }))).toBe(false);
    expect(next.click).not.toHaveBeenCalled();
  });
});

describe("pirep list new-tab click", () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div data-pirep-table>
        <table><tr class="fi-ta-row">
          <td><a href="/admin/pireps/7">JBU1</a></td>
          <td id="cell">Pilot</td>
        </tr></table>
      </div>`;
  });

  it("returns the row's ident link for a ctrl-click on another cell", () => {
    const event = new MouseEvent("click", { bubbles: true, ctrlKey: true });
    Object.defineProperty(event, "target", { value: document.getElementById("cell") });

    expect(newTabUrl(event)).toContain("/admin/pireps/7");
  });

  it("ignores a plain click", () => {
    const event = new MouseEvent("click", { bubbles: true });
    Object.defineProperty(event, "target", { value: document.getElementById("cell") });

    expect(newTabUrl(event)).toBeNull();
  });
});
