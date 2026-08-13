import { beforeEach, expect, it, vi } from "vitest";

import {
  bootstrap,
  editDashboardLayout,
  init,
  removeDashboardWidget,
  saveDashboardLayouts,
  serializeLayout,
} from "./dashboard/index";

beforeEach(() => {
  document.body.innerHTML = "";
  delete window.Alpine;
  delete window.Livewire;
  document.documentElement.style.setProperty("--primary-600", "#ff0000");
});

it("uses the active theme color for the strongest chart value", () => {
  const chart = document.createElement("div");
  chart.dataset.dashboardChart = "hbar";
  chart.dataset.chartPayload = JSON.stringify({
    labels: ["Lower", "Highest"],
    values: [10, 20],
  });
  document.body.appendChild(chart);

  bootstrap();

  const fills = Array.from(chart.querySelectorAll("rect"), (rect) => rect.getAttribute("fill"));

  expect(fills).toContain("rgb(255, 0, 0)");
});

it("fills the calendar chart width and height", () => {
  const chart = document.createElement("div");
  Object.defineProperties(chart, {
    clientWidth: { value: 800 },
    clientHeight: { value: 400 },
  });
  chart.dataset.dashboardChart = "calendar";
  chart.dataset.chartPayload = JSON.stringify({
    days: Array.from({ length: 7 }, (_, day) => ({
      date: `2026-08-${String(day + 6).padStart(2, "0")}`,
      label: "Wed",
      values: Array(24).fill(0),
    })),
    max: 0,
  });
  document.body.appendChild(chart);

  bootstrap();

  const cells = chart.querySelectorAll('rect[role="link"]');
  const lastCell = cells[23];
  const lastRowCell = cells[cells.length - 1];

  expect(chart.querySelector("svg").getAttribute("viewBox")).toBe("0 0 800 400");
  expect(chart.querySelector("svg").getAttribute("preserveAspectRatio")).toBe("xMidYMid meet");
  expect(chart.querySelector("svg").style.height).toBe("100%");
  expect(cells).toHaveLength(168);
  expect(
    Number(lastCell.getAttribute("x")) + Number(lastCell.getAttribute("width")),
  ).toBeGreaterThan(750);
  expect(
    Number(lastRowCell.getAttribute("y")) + Number(lastRowCell.getAttribute("height")),
  ).toBeGreaterThan(350);
});

it("fits bar charts inside the resized widget", () => {
  const chart = document.createElement("div");
  Object.defineProperties(chart, {
    clientWidth: { value: 835 },
    clientHeight: { value: 152 },
  });
  chart.dataset.dashboardChart = "bar";
  chart.dataset.chartPayload = JSON.stringify({
    labels: ["Mon", "Tue", "Wed"],
    values: [2, 4, 3],
  });
  document.body.appendChild(chart);

  bootstrap();

  const renderedChart = chart.querySelector("svg");

  expect(renderedChart.getAttribute("viewBox")).toBe("0 0 835 152");
  expect(renderedChart.style.height).toBe("100%");
});

it("serializes the package GridStack positions", () => {
  const canvas = document.createElement("div");
  const grid = document.createElement("div");
  const item = document.createElement("div");
  canvas.className = "dashboard-canvas";
  grid.className = "grid-stack";
  grid.dataset.section = "main";
  item.setAttribute("gs-id", "42");
  grid.gridstack = { engine: { nodes: [{ el: item, x: 0, y: 2, w: 12, h: 2 }] } };
  canvas.appendChild(grid);

  expect(serializeLayout(canvas)).toEqual([
    {
      id: 42,
      section: "main",
      x: 0,
      y: 2,
      w: 12,
      h: 2,
    },
  ]);
});

it("uses the package GridStack instance and enables controls only in edit mode", async () => {
  const setEditable = vi.fn();
  window.Alpine = { $data: () => ({ setEditable }) };
  document.body.innerHTML = `
    <div wire:id="dashboard">
      <button data-dashboard-layout-edit></button>
      <button class="hidden" data-dashboard-layout-save></button>
      <div class="dashboard-canvas is-readonly">
        <div class="grid-stack" data-section="main"></div>
      </div>
    </div>
  `;

  const grid = document.querySelector(".grid-stack");
  const item = document.createElement("div");
  item.setAttribute("gs-id", "42");
  grid.gridstack = {
    engine: { nodes: [{ el: item, x: 0, y: 0, w: 12, h: 2 }] },
    on: vi.fn(),
  };

  editDashboardLayout();

  expect(setEditable).toHaveBeenCalledWith(true);
  expect(document.querySelector(".dashboard-canvas").classList).toContain(
    "dashboard-layout-editing",
  );
  expect(document.querySelector("[data-dashboard-layout-edit]").classList).toContain("hidden");
  expect(document.querySelector("[data-dashboard-layout-save]").classList).not.toContain("hidden");
  const persistLayout = vi.fn().mockResolvedValue(undefined);
  window.Livewire = { find: () => ({ persistLayout }) };
  await saveDashboardLayouts();

  expect(persistLayout).toHaveBeenCalledWith([
    { id: 42, section: "main", x: 0, y: 0, w: 12, h: 2 },
  ]);
  expect(setEditable).toHaveBeenLastCalledWith(false);
  expect(document.querySelector(".dashboard-canvas").classList).not.toContain(
    "dashboard-layout-editing",
  );
  expect(document.querySelector("[data-dashboard-layout-edit]").classList).not.toContain("hidden");
  expect(document.querySelector("[data-dashboard-layout-save]").classList).toContain("hidden");
});

it("removes a widget through GridStack instead of tearing down the DOM node itself", () => {
  document.body.innerHTML = `
    <div wire:id="dashboard">
      <div class="dashboard-canvas">
        <div class="grid-stack">
          <div class="grid-stack-item" gs-id="7"></div>
        </div>
      </div>
    </div>
  `;
  const itemEl = document.querySelector('.grid-stack-item[gs-id="7"]');
  const removeWidget = vi.fn();
  document.querySelector(".grid-stack").gridstack = { removeWidget };
  window.Livewire = { find: () => ({ deleteDashboardWidget: vi.fn() }) };

  removeDashboardWidget(7);

  expect(removeWidget).toHaveBeenCalledWith(itemEl);
  // A stale GridStack engine node corrupts the next serializeLayout(), so the
  // node must still be in the DOM here — only the mocked grid.removeWidget()
  // is allowed to take it out, and this stub deliberately doesn't.
  expect(document.body.contains(itemEl)).toBe(true);
});

it("tells the Livewire component to delete the widget by its numeric gs-id", () => {
  document.body.innerHTML = `
    <div wire:id="dashboard">
      <div class="dashboard-canvas">
        <div class="grid-stack">
          <div class="grid-stack-item" gs-id="7"></div>
        </div>
      </div>
    </div>
  `;
  document.querySelector(".grid-stack").gridstack = { removeWidget: vi.fn() };
  const deleteDashboardWidget = vi.fn();
  window.Livewire = { find: () => ({ deleteDashboardWidget }) };

  removeDashboardWidget(7);

  expect(deleteDashboardWidget).toHaveBeenCalledWith(7);
});

it("quietly does nothing when no widget matches the given id", () => {
  document.body.innerHTML = `
    <div wire:id="dashboard">
      <div class="dashboard-canvas">
        <div class="grid-stack"></div>
      </div>
    </div>
  `;
  document.querySelector(".grid-stack").gridstack = { removeWidget: vi.fn() };
  const deleteDashboardWidget = vi.fn();
  window.Livewire = { find: () => ({ deleteDashboardWidget }) };

  expect(() => removeDashboardWidget(99)).not.toThrow();

  expect(deleteDashboardWidget).not.toHaveBeenCalled();
});

it("restores edit mode on boot when the session flag is set, and clears it", () => {
  const setEditable = vi.fn();
  window.Alpine = { $data: () => ({ setEditable }) };
  document.body.innerHTML = `
    <div class="dashboard-canvas">
      <div class="grid-stack" data-section="main"></div>
    </div>
  `;
  document.querySelector(".grid-stack").gridstack = { on: vi.fn() };
  window.sessionStorage.setItem("phpvms:dashboard-editing", "1");

  init();

  expect(setEditable).toHaveBeenCalledWith(true);
  expect(document.querySelector(".dashboard-canvas").classList).toContain(
    "dashboard-layout-editing",
  );
  expect(window.sessionStorage.getItem("phpvms:dashboard-editing")).toBeNull();
});

it("waits for the grid to boot before restoring edit mode, clearing the flag either way", () => {
  const setEditable = vi.fn();
  window.Alpine = { $data: () => ({ setEditable }) };
  // No `.gridstack` yet: this is the chunk resolving before Alpine boots.
  document.body.innerHTML = `
    <div class="dashboard-canvas is-readonly">
      <div class="grid-stack" data-section="main"></div>
    </div>
  `;
  window.sessionStorage.setItem("phpvms:dashboard-editing", "1");

  init();

  // Restoring here would leave edit chrome over a still-static grid.
  expect(setEditable).not.toHaveBeenCalled();
  expect(document.querySelector(".dashboard-canvas").classList).not.toContain(
    "dashboard-layout-editing",
  );
  // The flag is gone regardless, so it can't strand a later page load.
  expect(window.sessionStorage.getItem("phpvms:dashboard-editing")).toBeNull();

  document.querySelector(".grid-stack").gridstack = { on: vi.fn() };
  window.dispatchEvent(new CustomEvent("dashboard-grid:ready"));

  expect(setEditable).toHaveBeenCalledWith(true);
  expect(document.querySelector(".dashboard-canvas").classList).toContain(
    "dashboard-layout-editing",
  );
});

it("does not enter edit mode on boot when the session flag is absent", () => {
  const setEditable = vi.fn();
  window.Alpine = { $data: () => ({ setEditable }) };
  document.body.innerHTML = `
    <div class="dashboard-canvas">
      <div class="grid-stack" data-section="main"></div>
    </div>
  `;
  window.sessionStorage.removeItem("phpvms:dashboard-editing");

  init();

  expect(setEditable).not.toHaveBeenCalled();
  expect(document.querySelector(".dashboard-canvas").classList).not.toContain(
    "dashboard-layout-editing",
  );
});

it("reveals the add-widget button alongside save, and hides it alongside save again", async () => {
  document.body.innerHTML = `
    <div wire:id="dashboard">
      <button class="hidden" data-dashboard-layout-add></button>
      <div class="dashboard-canvas is-readonly">
        <div class="grid-stack" data-section="main"></div>
      </div>
    </div>
  `;
  window.Alpine = { $data: () => ({ setEditable: vi.fn() }) };
  document.querySelector(".grid-stack").gridstack = { engine: { nodes: [] }, on: vi.fn() };

  editDashboardLayout();

  expect(document.querySelector("[data-dashboard-layout-add]").classList).not.toContain("hidden");

  window.Livewire = { find: () => ({ persistLayout: vi.fn().mockResolvedValue(undefined) }) };
  await saveDashboardLayouts();

  expect(document.querySelector("[data-dashboard-layout-add]").classList).toContain("hidden");
});
