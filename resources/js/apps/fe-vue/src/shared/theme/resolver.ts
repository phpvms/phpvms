import type {
  ButtonAlignment,
  ButtonShape,
  ButtonWeight,
  Density,
  InputAppearance,
  ThemeDocumentV1,
} from "./schema";

export interface ResolvedTheme {
  props: {
    button?: NonNullable<NonNullable<ThemeDocumentV1["nuxtUi"]["components"]>["button"]>["props"];
    input?: NonNullable<NonNullable<ThemeDocumentV1["nuxtUi"]["components"]>["input"]>["props"];
  };
  ui: {
    button?: { base: string };
    input?: { base: string };
  };
}

export const BUTTON_ALIGNMENT_CLASSES: Record<ButtonAlignment, string> = {
  start: "justify-start",
  center: "justify-center",
  end: "justify-end",
};

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
  const { button, input } = document.nuxtUi.components ?? {};
  const buttonClasses =
    button?.style && Object.keys(button.style).length > 0
      ? [
          button.style.alignment && BUTTON_ALIGNMENT_CLASSES[button.style.alignment],
          button.style.shape && BUTTON_SHAPE_CLASSES[button.style.shape],
          button.style.weight && BUTTON_WEIGHT_CLASSES[button.style.weight],
          CONTROL_DENSITY_CLASSES[document.phpvms.density],
        ].filter(Boolean)
      : [];
  const inputClasses = input?.style?.appearance
    ? [INPUT_APPEARANCE_CLASSES[input.style.appearance]]
    : [];

  return {
    props: {
      ...(button?.props && Object.keys(button.props).length > 0
        ? { button: { ...button.props } }
        : {}),
      ...(input?.props && Object.keys(input.props).length > 0 ? { input: { ...input.props } } : {}),
    },
    ui: {
      ...(buttonClasses.length > 0 ? { button: { base: buttonClasses.join(" ") } } : {}),
      ...(inputClasses.length > 0 ? { input: { base: inputClasses.join(" ") } } : {}),
    },
  };
}
