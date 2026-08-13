import { describe, expect, it } from "vitest";
import rawTheme from "../fixtures/nuxt-ui-themes-b3b334c4.json";
import {
  DEFAULT_THEME_DOCUMENT,
  THEME_MIGRATIONS,
  ThemeValidationError,
  defaultThemeDocument,
  initialThemeDocument,
  normalizeThemeDocument,
  parseThemeDocument,
} from "@/shared/theme";

// Current primary fixture provenance:
// mattycraig/nuxt-theme-builder@b3b334c459ab2fa97e975a31005468b75102a62d
// app/composables/useThemeExport.ts:85-87 and app/utils/defaults.ts:81-117.
describe("Skylight theme schema", () => {
  it("normalizes the current raw Theme Builder export with phpVMS defaults", () => {
    expect(normalizeThemeDocument(rawTheme)).toEqual(DEFAULT_THEME_DOCUMENT);
  });

  it("fills upstream backward-compatible dark fields from light fields", () => {
    const legacyRaw: Partial<typeof rawTheme> = structuredClone(rawTheme);
    delete legacyRaw.darkColors;
    delete legacyRaw.darkColorShades;
    delete legacyRaw.darkNeutral;
    delete legacyRaw.darkRadius;
    delete legacyRaw.darkFont;
    const document = normalizeThemeDocument(legacyRaw);

    expect(document.nuxtUi.theme.darkColors).toEqual(document.nuxtUi.theme.colors);
    expect(document.nuxtUi.theme.darkColorShades).toEqual(document.nuxtUi.theme.colorShades);
    expect(document.nuxtUi.theme.darkNeutral).toBe(document.nuxtUi.theme.neutral);
    expect(document.nuxtUi.theme.darkRadius).toBe(document.nuxtUi.theme.radius);
    expect(document.nuxtUi.theme.darkFont).toBe(document.nuxtUi.theme.font);
  });

  it("uses a fresh version-1 fallback only for a null Inertia theme prop", () => {
    const first = initialThemeDocument(null);
    const second = initialThemeDocument(null);

    expect(first).toEqual(DEFAULT_THEME_DOCUMENT);
    expect(second).toEqual(DEFAULT_THEME_DOCUMENT);
    expect(first).not.toBe(second);
    expect(initialThemeDocument(DEFAULT_THEME_DOCUMENT)).toEqual(DEFAULT_THEME_DOCUMENT);
    expect(() => initialThemeDocument(undefined)).toThrowError("theme: must be an object");
  });

  it("rejects arbitrary component classes and slot objects", () => {
    const withClass = defaultThemeDocument() as unknown as Record<string, any>;
    withClass.nuxtUi.components.button.style.class = "rounded-[999px]";

    expect(() => parseThemeDocument(withClass)).toThrowError(
      new ThemeValidationError("theme.nuxtUi.components.button.style.class", "is not supported"),
    );

    const withSlots = defaultThemeDocument() as unknown as Record<string, any>;
    withSlots.nuxtUi.components.button.ui = { base: "p-12" };
    expect(() => parseThemeDocument(withSlots)).toThrowError(
      /theme\.nuxtUi\.components\.button\.ui/,
    );
  });

  it("rejects unsupported button alignment values", () => {
    const document = defaultThemeDocument() as unknown as Record<string, any>;
    document.nuxtUi.components.button.style.alignment = "space-between";

    expect(() => parseThemeDocument(document)).toThrowError(
      /theme\.nuxtUi\.components\.button\.style\.alignment/,
    );
  });

  it("preserves omitted component overrides for Nuxt UI defaults", () => {
    const document = defaultThemeDocument() as unknown as Record<string, any>;
    document.nuxtUi.components = {
      button: {
        props: { color: "warning" },
        style: { shape: "pill" },
      },
      input: {},
    };

    expect(parseThemeDocument(document).nuxtUi.components).toEqual(document.nuxtUi.components);

    document.nuxtUi.components = {};

    expect(parseThemeDocument(document).nuxtUi.components).toEqual({});

    delete document.nuxtUi.components;

    expect(parseThemeDocument(document).nuxtUi.components).toBeUndefined();
  });

  it("rejects unsupported raw export fields", () => {
    expect(() => normalizeThemeDocument({ ...rawTheme, class: "text-red-500" })).toThrowError(
      /theme\.nuxtUi\.theme\.class/,
    );
  });

  it("has no invented migrations and rejects old, unknown, and newer versions", () => {
    expect(THEME_MIGRATIONS).toEqual({});

    for (const version of [0, 2, 999]) {
      const document = { ...defaultThemeDocument(), version };
      expect(() => parseThemeDocument(document)).toThrowError(
        `theme.version: unsupported theme document version: ${version}`,
      );
    }
  });
});
