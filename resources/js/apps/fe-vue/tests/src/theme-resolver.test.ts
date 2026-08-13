import { describe, expect, it } from "vitest";
import {
  BUTTON_ALIGNMENTS,
  BUTTON_ALIGNMENT_CLASSES,
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
    expect(Object.keys(BUTTON_ALIGNMENT_CLASSES).sort()).toEqual([...BUTTON_ALIGNMENTS].sort());
    expect(Object.keys(BUTTON_SHAPE_CLASSES).sort()).toEqual([...BUTTON_SHAPES].sort());
    expect(Object.keys(BUTTON_WEIGHT_CLASSES).sort()).toEqual([...BUTTON_WEIGHTS].sort());
    expect(Object.keys(CONTROL_DENSITY_CLASSES).sort()).toEqual([...DENSITIES].sort());
    expect(Object.keys(INPUT_APPEARANCE_CLASSES).sort()).toEqual([...INPUT_APPEARANCES].sort());
  });

  it("resolves curated component props and compiled ui classes synchronously", () => {
    const document = defaultThemeDocument();
    document.nuxtUi.components = {
      button: {
        props: { color: "warning", variant: "soft", size: "lg" },
        style: {
          alignment: "end",
          shape: "pill",
          weight: "semibold",
        },
      },
      input: {
        props: { color: "error", size: "sm" },
        style: { appearance: "boxed" },
      },
    };
    document.phpvms.density = "comfortable";

    expect(resolveTheme(document)).toEqual({
      props: {
        button: { color: "warning", variant: "soft", size: "lg" },
        input: { color: "error", size: "sm" },
      },
      ui: {
        button: { base: "justify-end rounded-full font-semibold gap-2" },
        input: { base: "rounded-md border shadow-xs" },
      },
    });
  });

  it("omits absent overrides so Nuxt UI defaults apply", () => {
    const document = defaultThemeDocument();
    document.nuxtUi.components = {};

    expect(resolveTheme(document)).toEqual({ props: {}, ui: {} });

    document.nuxtUi.components = {
      button: { props: {}, style: {} },
      input: { props: {}, style: {} },
    };

    expect(resolveTheme(document)).toEqual({ props: {}, ui: {} });

    document.nuxtUi.components = {
      button: { props: { color: "warning" } },
      input: { style: { appearance: "boxed" } },
    };

    expect(resolveTheme(document)).toEqual({
      props: { button: { color: "warning" } },
      ui: { input: { base: "rounded-md border shadow-xs" } },
    });
  });
});
