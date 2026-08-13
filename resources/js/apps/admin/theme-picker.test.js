/**
 * Vitest coverage for the topbar theme picker after the brand-colour split
 * (group 5 of the add-branding-page change): brand colour moved to the
 * Branding admin page and is applied server-side via
 * `AdminPanelProvider::colors()`. This file keeps only appearance and
 * density -- both stay per-user, persisted to `localStorage`, and never
 * touch the settings table.
 */
import { beforeEach, expect, it, vi } from "vitest";

const themeState = new Map();

vi.stubGlobal("localStorage", {
  getItem: (key) => themeState.get(key) ?? null,
  setItem: (key, value) => themeState.set(key, value),
});

document.body.innerHTML = `
  <div class="fi-theme-picker">
    <button data-mode="light" aria-pressed="false"></button>
    <button data-mode="dark" aria-pressed="false"></button>
    <button data-density="compact" aria-pressed="false"></button>
    <button data-density="comfortable" aria-pressed="false"></button>
  </div>
`;

await import("./theme-picker");

beforeEach(() => {
  themeState.clear();
});

it("persists density to localStorage and applies it to the document", () => {
  document
    .querySelector('[data-density="comfortable"]')
    .dispatchEvent(new MouseEvent("click", { bubbles: true }));

  expect(document.documentElement.dataset.density).toBe("comfortable");
  expect(JSON.parse(themeState.get("phpvms-console-theme")).density).toBe("comfortable");
  expect(themeState.has("branding.brand_color")).toBe(false);
});

it("dispatches theme-changed for appearance instead of writing to the store", () => {
  const listener = vi.fn();
  window.addEventListener("theme-changed", listener);

  document
    .querySelector('[data-mode="dark"]')
    .dispatchEvent(new MouseEvent("click", { bubbles: true }));

  expect(listener).toHaveBeenCalledOnce();
  expect(listener.mock.calls[0][0].detail).toBe("dark");

  window.removeEventListener("theme-changed", listener);
});

it("ignores a stale brand colour already sitting in localStorage", async () => {
  // A browser that used the old picker still has `{brand, density}` saved.
  // Fresh-import the module against that stored value: DEFAULTS no longer
  // names `brand`, and nothing in the module reads `state.brand` back out,
  // so it never reaches the DOM.
  themeState.set("phpvms-console-theme", JSON.stringify({ brand: "#ff0000", density: "compact" }));

  vi.resetModules();
  await import("./theme-picker");

  expect(document.documentElement.style.getPropertyValue("--primary-600")).toBe("");
  expect(document.documentElement.dataset.density).toBe("compact");
});
