import { describe, it, expect, beforeAll } from "vitest";
import { defineElement } from "@/elements/define-element";
import PvStatGroup from "@/elements/PvStatGroup.ce.vue";

beforeAll(() => defineElement("pv-stat-group-test", PvStatGroup));

async function render(markup: string): Promise<HTMLElement> {
  document.body.innerHTML = markup;
  await customElements.whenDefined("pv-stat-group-test");
  return document.body.firstElementChild as HTMLElement;
}

describe("PvStatGroup (pv-stat-group)", () => {
  it("renders a sensible default with zero attributes set (not blank)", async () => {
    const el = await render("<pv-stat-group-test></pv-stat-group-test>");
    expect(el.textContent).toContain("No stats configured.");
  });

  it("renders configured stats from a JSON items attribute", async () => {
    const items = JSON.stringify([
      { label: "Hours", value: "142.5" },
      { label: "Flights", value: "87" },
    ]);
    const el = await render(`<pv-stat-group-test items='${items}'></pv-stat-group-test>`);
    expect(el.textContent).toContain("Hours");
    expect(el.textContent).toContain("142.5");
    expect(el.textContent).toContain("Flights");
    expect(el.textContent).toContain("87");
  });

  it("fails visibly (not blank, not thrown) on malformed JSON", async () => {
    const el = await render(`<pv-stat-group-test items="{not json"></pv-stat-group-test>`);
    const alert = el.querySelector('[role="alert"]');
    expect(alert).not.toBeNull();
    expect(alert?.getAttribute("data-pv-element-failed")).toBe("pv-stat-group");
    expect(el.textContent).toContain("Malformed");
  });

  it("fails visibly when items is valid JSON but not a {label, value}[] shape", async () => {
    const el = await render(
      `<pv-stat-group-test items='["just", "strings"]'></pv-stat-group-test>`,
    );
    expect(el.querySelector('[role="alert"]')).not.toBeNull();
  });

  it("fails visibly when items is a JSON object, not an array", async () => {
    const el = await render(
      `<pv-stat-group-test items='{"label":"x","value":"y"}'></pv-stat-group-test>`,
    );
    expect(el.querySelector('[role="alert"]')).not.toBeNull();
  });
});
