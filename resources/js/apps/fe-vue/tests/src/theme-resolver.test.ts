import { describe, expect, it } from "vitest";
import {
  BUTTON_SHAPES,
  BUTTON_SHAPE_CLASSES,
  BUTTON_WEIGHTS,
  BUTTON_WEIGHT_CLASSES,
  CONTROL_DENSITY_CLASSES,
  DENSITIES,
  INPUT_APPEARANCES,
  INPUT_APPEARANCE_CLASSES,
  defaultThemeDocument,
  resolveTheme,
} from "@/shared/theme";

describe("Skylight UTheme resolver", () => {
  it("has one compiled mapping for every public finite identifier", () => {
    expect(Object.keys(BUTTON_SHAPE_CLASSES).sort()).toEqual([...BUTTON_SHAPES].sort());
    expect(Object.keys(BUTTON_WEIGHT_CLASSES).sort()).toEqual([...BUTTON_WEIGHTS].sort());
    expect(Object.keys(CONTROL_DENSITY_CLASSES).sort()).toEqual([...DENSITIES].sort());
    expect(Object.keys(INPUT_APPEARANCE_CLASSES).sort()).toEqual([...INPUT_APPEARANCES].sort());
  });

  it("resolves curated component props and compiled ui classes synchronously", () => {
    const document = defaultThemeDocument();
    document.nuxtUi.components.button.props = { color: "warning", variant: "soft", size: "lg" };
    document.nuxtUi.components.button.style = { shape: "pill", weight: "semibold" };
    document.nuxtUi.components.input.props = { color: "error", size: "sm" };
    document.nuxtUi.components.input.style.appearance = "boxed";
    document.phpvms.density = "comfortable";

    expect(resolveTheme(document)).toEqual({
      props: {
        button: { color: "warning", variant: "soft", size: "lg" },
        input: { color: "error", size: "sm" },
      },
      ui: {
        button: { base: "rounded-full font-semibold gap-2" },
        input: { base: "rounded-md border shadow-xs" },
      },
    });
  });
});
