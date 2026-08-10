import type {
  ButtonShape,
  ButtonWeight,
  Density,
  InputAppearance,
  ThemeDocumentV1,
} from "./schema";

export interface ResolvedTheme {
  props: {
    button: ThemeDocumentV1["nuxtUi"]["components"]["button"]["props"];
    input: ThemeDocumentV1["nuxtUi"]["components"]["input"]["props"];
  };
  ui: {
    button: { base: string };
    input: { base: string };
  };
}

export const BUTTON_SHAPE_CLASSES: Record<ButtonShape, string> = {
  square: "rounded-none",
  rounded: "rounded-md",
  pill: "rounded-full",
};

export const BUTTON_WEIGHT_CLASSES: Record<ButtonWeight, string> = {
  normal: "font-normal",
  medium: "font-medium",
  semibold: "font-semibold",
};

export const CONTROL_DENSITY_CLASSES: Record<Density, string> = {
  compact: "gap-1",
  comfortable: "gap-2",
};

export const INPUT_APPEARANCE_CLASSES: Record<InputAppearance, string> = {
  boxed: "rounded-md border shadow-xs",
  underline: "rounded-none border-0 border-b bg-transparent shadow-none",
};

export function resolveTheme(document: ThemeDocumentV1): ResolvedTheme {
  const { button, input } = document.nuxtUi.components;
  return {
    props: {
      button: { ...button.props },
      input: { ...input.props },
    },
    ui: {
      button: {
        base: [
          BUTTON_SHAPE_CLASSES[button.style.shape],
          BUTTON_WEIGHT_CLASSES[button.style.weight],
          CONTROL_DENSITY_CLASSES[document.phpvms.density],
        ].join(" "),
      },
      input: {
        base: INPUT_APPEARANCE_CLASSES[input.style.appearance],
      },
    },
  };
}
