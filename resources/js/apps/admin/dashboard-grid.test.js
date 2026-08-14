import { expect, it, vi } from "vitest";

it("initializes one non-compacting locked GridStack instance", async () => {
  const data = vi.fn();
  window.Alpine = { data };

  await import("./dashboard/grid.js");
  document.dispatchEvent(new Event("alpine:init"));

  expect(data).toHaveBeenCalledOnce();
  expect(data.mock.calls[0][0]).toBe("phpvmsDashboardGrid");

  const factory = data.mock.calls[0][1];
  const gridElement = document.createElement("div");
  gridElement.className = "grid-stack";
  gridElement.setAttribute("gs-column", "12");
  gridElement.setAttribute("gs-cell-height", "100");

  const grid = {
    on: vi.fn(),
    setStatic: vi.fn(),
    destroy: vi.fn(),
  };
  window.GridStack = {
    init: vi.fn(() => {
      gridElement.gridstack = grid;

      return grid;
    }),
  };

  const component = factory();
  component.$el = document.createElement("div");
  component.$el.appendChild(gridElement);
  component.bootGrids();

  expect(window.GridStack.init).toHaveBeenCalledWith(
    expect.objectContaining({ float: true, staticGrid: true }),
    gridElement,
  );
  // The instance must stay off the component: Alpine would proxy it, and
  // GridStack's dragstop identity check (`node.grid === this`) then fails.
  expect(component.grids).toBeUndefined();

  component.setEditable(true);

  expect(grid.setStatic).toHaveBeenCalledWith(false);
});
