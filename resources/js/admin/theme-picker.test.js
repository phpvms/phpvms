import { expect, it, vi } from "vitest";

const updateChartTheme = vi.fn();
const themeState = new Map();

vi.stubGlobal("localStorage", {
  getItem: (key) => themeState.get(key) ?? null,
  setItem: (key, value) => themeState.set(key, value),
});
document.body.innerHTML = `
  <div class="fi-wi-chart-frame"></div>
  <div class="fi-theme-picker">
    <input data-hex-color value="#067ec1">
  </div>
`;

vi.stubGlobal("requestAnimationFrame", (callback) => callback());
window.Alpine = {
  $data: () => ({ updateChartTheme }),
};

await import("./theme-picker");

it("refreshes native Filament charts when the brand color changes", () => {
  updateChartTheme.mockClear();

  const picker = document.querySelector("[data-hex-color]");
  picker.value = "#ff0000";
  picker.dispatchEvent(new Event("input", { bubbles: true }));

  expect(document.documentElement.style.getPropertyValue("--primary-600")).toBe("#ff0000");
  expect(updateChartTheme).toHaveBeenCalledOnce();
});
