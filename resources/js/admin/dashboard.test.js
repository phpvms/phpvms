import { beforeEach, expect, it, vi } from "vitest";

import {
  bootstrap,
  editDashboardLayout,
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
