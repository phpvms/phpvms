import defaultsJson from "../../../schemas/skylight-theme-v1.defaults.json";

export const THEME_VERSION = 1 as const;

export const PALETTES = [
  "red",
  "orange",
  "amber",
  "yellow",
  "lime",
  "green",
  "emerald",
  "teal",
  "cyan",
  "sky",
  "blue",
  "indigo",
  "violet",
  "purple",
  "fuchsia",
  "pink",
  "rose",
  "slate",
  "gray",
  "zinc",
  "neutral",
  "stone",
  "taupe",
  "mauve",
  "mist",
  "olive",
] as const;

export const NEUTRAL_PALETTES = [
  "slate",
  "gray",
  "zinc",
  "neutral",
  "stone",
  "taupe",
  "mauve",
  "mist",
  "olive",
] as const;

export const SHADES = [
  "white",
  "black",
  "50",
  "100",
  "200",
  "300",
  "400",
  "500",
  "600",
  "700",
  "800",
  "900",
  "950",
] as const;

export const FONTS = [
  "Public Sans",
  "DM Sans",
  "Figtree",
  "Geist",
  "Inter",
  "Lato",
  "Montserrat",
  "Nunito",
  "Open Sans",
  "Outfit",
  "Plus Jakarta Sans",
  "Poppins",
  "Raleway",
  "Roboto",
  "Source Sans 3",
  "Space Grotesk",
  "Work Sans",
  "Lora",
  "Merriweather",
  "Playfair Display",
  "Source Serif 4",
  "Libre Baskerville",
  "DM Serif Display",
  "Crimson Text",
  "JetBrains Mono",
  "Fira Code",
  "Source Code Pro",
  "IBM Plex Mono",
  "Space Mono",
  "Sora",
  "Archivo",
  "Lexend",
  "Urbanist",
  "Bricolage Grotesque",
] as const;

export const SEMANTIC_COLORS = [
  "primary",
  "secondary",
  "success",
  "info",
  "warning",
  "error",
] as const;

export const BUTTON_COLORS = [...SEMANTIC_COLORS, "neutral"] as const;
export const BUTTON_VARIANTS = ["solid", "outline", "soft", "subtle", "ghost", "link"] as const;
export const CONTROL_SIZES = ["xs", "sm", "md", "lg", "xl"] as const;
export const BUTTON_ALIGNMENTS = ["start", "center", "end"] as const;
export const BUTTON_SHAPES = ["square", "rounded", "pill"] as const;
export const BUTTON_WEIGHTS = ["normal", "medium", "semibold"] as const;
export const INPUT_APPEARANCES = ["boxed", "underline"] as const;
export const DENSITIES = ["compact", "comfortable"] as const;
export const NAVIGATION_LAYOUTS = ["sidebar", "top"] as const;

type ValueOf<T extends readonly string[]> = T[number];

export type Palette = ValueOf<typeof PALETTES>;
export type NeutralPalette = ValueOf<typeof NEUTRAL_PALETTES>;
export type Shade = ValueOf<typeof SHADES>;
export type Font = ValueOf<typeof FONTS>;
export type SemanticColor = ValueOf<typeof SEMANTIC_COLORS>;
export type ButtonColor = ValueOf<typeof BUTTON_COLORS>;
export type ButtonVariant = ValueOf<typeof BUTTON_VARIANTS>;
export type ControlSize = ValueOf<typeof CONTROL_SIZES>;
export type ButtonAlignment = ValueOf<typeof BUTTON_ALIGNMENTS>;
export type ButtonShape = ValueOf<typeof BUTTON_SHAPES>;
export type ButtonWeight = ValueOf<typeof BUTTON_WEIGHTS>;
export type InputAppearance = ValueOf<typeof INPUT_APPEARANCES>;
export type Density = ValueOf<typeof DENSITIES>;
export type NavigationLayout = ValueOf<typeof NAVIGATION_LAYOUTS>;

export type SemanticPalette = Record<SemanticColor, Palette>;
export type SemanticShades = Record<SemanticColor, Shade>;

export interface TokenOverrides {
  text: Record<"dimmed" | "muted" | "toned" | "default" | "highlighted" | "inverted", Shade>;
  bg: Record<"default" | "muted" | "elevated" | "accented" | "inverted", Shade>;
  border: Record<"default" | "muted" | "accented" | "inverted", Shade>;
}

export interface UpstreamTheme {
  colors: SemanticPalette;
  colorShades: SemanticShades;
  neutral: NeutralPalette;
  radius: number;
  font: Font;
  lightOverrides: TokenOverrides;
  darkOverrides: TokenOverrides;
  darkColors: SemanticPalette;
  darkColorShades: SemanticShades;
  darkNeutral: NeutralPalette;
  darkRadius: number;
  darkFont: Font;
}

export interface ThemeComponents {
  button?: {
    props?: { color?: ButtonColor; variant?: ButtonVariant; size?: ControlSize };
    style?: { alignment?: ButtonAlignment; shape?: ButtonShape; weight?: ButtonWeight };
  };
  input?: {
    props?: { color?: ButtonColor; size?: ControlSize };
    style?: { appearance?: InputAppearance };
  };
}

export interface PhpVmsThemeSettings {
  density: Density;
  navigation: NavigationLayout;
  tableDensity: Density;
}

export interface ThemeDocumentV1 {
  version: typeof THEME_VERSION;
  nuxtUi: {
    theme: UpstreamTheme;
    components?: ThemeComponents;
  };
  phpvms: PhpVmsThemeSettings;
}

export interface ThemeInertiaProps {
  theme: ThemeDocumentV1 | null;
}

export class ThemeValidationError extends Error {
  constructor(
    public readonly path: string,
    message: string,
  ) {
    super(`${path}: ${message}`);
    this.name = "ThemeValidationError";
  }
}

type UnknownRecord = Record<string, unknown>;

function objectAt(value: unknown, path: string, keys: readonly string[]): UnknownRecord {
  if (value === null || typeof value !== "object" || Array.isArray(value)) {
    throw new ThemeValidationError(path, "must be an object");
  }

  const object = value as UnknownRecord;
  const unknown = Object.keys(object).find((key) => !keys.includes(key));
  if (unknown) {
    throw new ThemeValidationError(`${path}.${unknown}`, "is not supported");
  }

  return object;
}

function required(object: UnknownRecord, key: string, path: string): unknown {
  if (!Object.hasOwn(object, key)) {
    throw new ThemeValidationError(`${path}.${key}`, "is required");
  }
  return object[key];
}

function optionalObjectAt(
  object: UnknownRecord,
  key: string,
  path: string,
  keys: readonly string[],
): UnknownRecord | undefined {
  return Object.hasOwn(object, key) ? objectAt(object[key], `${path}.${key}`, keys) : undefined;
}

function enumAt<const T extends readonly string[]>(
  value: unknown,
  path: string,
  values: T,
): T[number] {
  if (typeof value !== "string" || !values.includes(value)) {
    throw new ThemeValidationError(path, `must be one of: ${values.join(", ")}`);
  }
  return value as T[number];
}

function numberAt(value: unknown, path: string): number {
  if (typeof value !== "number" || !Number.isFinite(value) || value < 0 || value > 2) {
    throw new ThemeValidationError(path, "must be a finite number from 0 through 2");
  }
  return value;
}

function recordAt<const T extends readonly string[], const V extends readonly string[]>(
  value: unknown,
  path: string,
  keys: T,
  values: V,
): Record<T[number], V[number]> {
  const object = objectAt(value, path, keys);
  return Object.fromEntries(
    keys.map((key) => [key, enumAt(required(object, key, path), `${path}.${key}`, values)]),
  ) as Record<T[number], V[number]>;
}

function tokenOverridesAt(value: unknown, path: string): TokenOverrides {
  const object = objectAt(value, path, ["text", "bg", "border"]);
  return {
    text: recordAt(
      required(object, "text", path),
      `${path}.text`,
      ["dimmed", "muted", "toned", "default", "highlighted", "inverted"] as const,
      SHADES,
    ),
    bg: recordAt(
      required(object, "bg", path),
      `${path}.bg`,
      ["default", "muted", "elevated", "accented", "inverted"] as const,
      SHADES,
    ),
    border: recordAt(
      required(object, "border", path),
      `${path}.border`,
      ["default", "muted", "accented", "inverted"] as const,
      SHADES,
    ),
  };
}

function upstreamThemeAt(
  value: unknown,
  path: string,
  allowLegacyDarkDefaults: boolean,
): UpstreamTheme {
  const keys = [
    "colors",
    "colorShades",
    "neutral",
    "radius",
    "font",
    "lightOverrides",
    "darkOverrides",
    "darkColors",
    "darkColorShades",
    "darkNeutral",
    "darkRadius",
    "darkFont",
  ] as const;
  const object = objectAt(value, path, keys);
  const colors = recordAt(
    required(object, "colors", path),
    `${path}.colors`,
    SEMANTIC_COLORS,
    PALETTES,
  );
  const colorShades = Object.hasOwn(object, "colorShades")
    ? recordAt(object.colorShades, `${path}.colorShades`, SEMANTIC_COLORS, SHADES)
    : allowLegacyDarkDefaults
      ? (Object.fromEntries(SEMANTIC_COLORS.map((key) => [key, "500"])) as SemanticShades)
      : recordAt(
          required(object, "colorShades", path),
          `${path}.colorShades`,
          SEMANTIC_COLORS,
          SHADES,
        );
  const neutral = enumAt(required(object, "neutral", path), `${path}.neutral`, NEUTRAL_PALETTES);
  const radius = numberAt(required(object, "radius", path), `${path}.radius`);
  const font = enumAt(required(object, "font", path), `${path}.font`, FONTS);

  const darkValue = <T>(key: string, fallback: T, parser: (input: unknown) => T): T => {
    if (Object.hasOwn(object, key)) return parser(object[key]);
    if (allowLegacyDarkDefaults) return structuredClone(fallback);
    return parser(required(object, key, path));
  };

  return {
    colors,
    colorShades,
    neutral,
    radius,
    font,
    lightOverrides: tokenOverridesAt(
      required(object, "lightOverrides", path),
      `${path}.lightOverrides`,
    ),
    darkOverrides: tokenOverridesAt(
      required(object, "darkOverrides", path),
      `${path}.darkOverrides`,
    ),
    darkColors: darkValue("darkColors", colors, (input) =>
      recordAt(input, `${path}.darkColors`, SEMANTIC_COLORS, PALETTES),
    ),
    darkColorShades: darkValue("darkColorShades", colorShades, (input) =>
      recordAt(input, `${path}.darkColorShades`, SEMANTIC_COLORS, SHADES),
    ),
    darkNeutral: darkValue("darkNeutral", neutral, (input) =>
      enumAt(input, `${path}.darkNeutral`, NEUTRAL_PALETTES),
    ),
    darkRadius: darkValue("darkRadius", radius, (input) => numberAt(input, `${path}.darkRadius`)),
    darkFont: darkValue("darkFont", font, (input) => enumAt(input, `${path}.darkFont`, FONTS)),
  };
}

function componentsAt(value: unknown, path: string): ThemeComponents {
  const object = objectAt(value, path, ["button", "input"]);
  const button = optionalObjectAt(object, "button", path, ["props", "style"]);
  const buttonProps = button
    ? optionalObjectAt(button, "props", `${path}.button`, ["color", "variant", "size"])
    : undefined;
  const buttonStyle = button
    ? optionalObjectAt(button, "style", `${path}.button`, ["alignment", "shape", "weight"])
    : undefined;
  const input = optionalObjectAt(object, "input", path, ["props", "style"]);
  const inputProps = input
    ? optionalObjectAt(input, "props", `${path}.input`, ["color", "size"])
    : undefined;
  const inputStyle = input
    ? optionalObjectAt(input, "style", `${path}.input`, ["appearance"])
    : undefined;

  return {
    ...(button
      ? {
          button: {
            ...(buttonProps
              ? {
                  props: {
                    ...(Object.hasOwn(buttonProps, "color")
                      ? {
                          color: enumAt(
                            buttonProps.color,
                            `${path}.button.props.color`,
                            BUTTON_COLORS,
                          ),
                        }
                      : {}),
                    ...(Object.hasOwn(buttonProps, "variant")
                      ? {
                          variant: enumAt(
                            buttonProps.variant,
                            `${path}.button.props.variant`,
                            BUTTON_VARIANTS,
                          ),
                        }
                      : {}),
                    ...(Object.hasOwn(buttonProps, "size")
                      ? {
                          size: enumAt(
                            buttonProps.size,
                            `${path}.button.props.size`,
                            CONTROL_SIZES,
                          ),
                        }
                      : {}),
                  },
                }
              : {}),
            ...(buttonStyle
              ? {
                  style: {
                    ...(Object.hasOwn(buttonStyle, "alignment")
                      ? {
                          alignment: enumAt(
                            buttonStyle.alignment,
                            `${path}.button.style.alignment`,
                            BUTTON_ALIGNMENTS,
                          ),
                        }
                      : {}),
                    ...(Object.hasOwn(buttonStyle, "shape")
                      ? {
                          shape: enumAt(
                            buttonStyle.shape,
                            `${path}.button.style.shape`,
                            BUTTON_SHAPES,
                          ),
                        }
                      : {}),
                    ...(Object.hasOwn(buttonStyle, "weight")
                      ? {
                          weight: enumAt(
                            buttonStyle.weight,
                            `${path}.button.style.weight`,
                            BUTTON_WEIGHTS,
                          ),
                        }
                      : {}),
                  },
                }
              : {}),
          },
        }
      : {}),
    ...(input
      ? {
          input: {
            ...(inputProps
              ? {
                  props: {
                    ...(Object.hasOwn(inputProps, "color")
                      ? {
                          color: enumAt(
                            inputProps.color,
                            `${path}.input.props.color`,
                            BUTTON_COLORS,
                          ),
                        }
                      : {}),
                    ...(Object.hasOwn(inputProps, "size")
                      ? { size: enumAt(inputProps.size, `${path}.input.props.size`, CONTROL_SIZES) }
                      : {}),
                  },
                }
              : {}),
            ...(inputStyle
              ? {
                  style: {
                    ...(Object.hasOwn(inputStyle, "appearance")
                      ? {
                          appearance: enumAt(
                            inputStyle.appearance,
                            `${path}.input.style.appearance`,
                            INPUT_APPEARANCES,
                          ),
                        }
                      : {}),
                  },
                }
              : {}),
          },
        }
      : {}),
  };
}

function phpVmsAt(value: unknown, path: string): PhpVmsThemeSettings {
  const object = objectAt(value, path, ["density", "navigation", "tableDensity"]);
  return {
    density: enumAt(required(object, "density", path), `${path}.density`, DENSITIES),
    navigation: enumAt(
      required(object, "navigation", path),
      `${path}.navigation`,
      NAVIGATION_LAYOUTS,
    ),
    tableDensity: enumAt(required(object, "tableDensity", path), `${path}.tableDensity`, DENSITIES),
  };
}

export const THEME_MIGRATIONS = Object.freeze({}) as Readonly<
  Record<number, (document: UnknownRecord) => ThemeDocumentV1>
>;

export function parseThemeDocument(value: unknown): ThemeDocumentV1 {
  const root = objectAt(value, "theme", ["version", "nuxtUi", "phpvms"]);
  const version = required(root, "version", "theme");
  if (version !== THEME_VERSION) {
    throw new ThemeValidationError(
      "theme.version",
      `unsupported theme document version: ${String(version)}`,
    );
  }

  const nuxtUi = objectAt(required(root, "nuxtUi", "theme"), "theme.nuxtUi", [
    "theme",
    "components",
  ]);
  return {
    version: THEME_VERSION,
    nuxtUi: {
      theme: upstreamThemeAt(
        required(nuxtUi, "theme", "theme.nuxtUi"),
        "theme.nuxtUi.theme",
        false,
      ),
      ...(Object.hasOwn(nuxtUi, "components")
        ? { components: componentsAt(nuxtUi.components, "theme.nuxtUi.components") }
        : {}),
    },
    phpvms: phpVmsAt(required(root, "phpvms", "theme"), "theme.phpvms"),
  };
}

export const DEFAULT_THEME_DOCUMENT = parseThemeDocument(defaultsJson);

export function defaultThemeDocument(): ThemeDocumentV1 {
  return structuredClone(DEFAULT_THEME_DOCUMENT);
}

export function normalizeThemeDocument(value: unknown): ThemeDocumentV1 {
  if (
    value !== null &&
    typeof value === "object" &&
    !Array.isArray(value) &&
    Object.hasOwn(value, "version")
  ) {
    return parseThemeDocument(value);
  }

  const defaults = defaultThemeDocument();
  return {
    version: THEME_VERSION,
    nuxtUi: {
      theme: upstreamThemeAt(value, "theme.nuxtUi.theme", true),
      components: defaults.nuxtUi.components,
    },
    phpvms: defaults.phpvms,
  };
}

export function initialThemeDocument(value: unknown): ThemeDocumentV1 {
  return value === null ? defaultThemeDocument() : parseThemeDocument(value);
}
