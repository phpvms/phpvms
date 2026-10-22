import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { CARTO_VOYAGER_URL, ESRI_SENTINEL, VACENTRAL_LIGHT_URL } from "./basemaps.ts";
import { resolveStyle } from "./style.ts";
import type { MapConfig } from "./types.ts";

const baseConfig: MapConfig = {
  basemapLight: CARTO_VOYAGER_URL,
  basemapDark: CARTO_VOYAGER_URL,
  customStyleUrl: null,
  customStyleApiKey: null,
  layers: [],
};

describe("resolveStyle", () => {
  let originalFetch: typeof fetch;

  beforeEach(() => {
    originalFetch = globalThis.fetch;
  });
  afterEach(() => {
    globalThis.fetch = originalFetch;
    vi.restoreAllMocks();
  });

  it("resolves the ESRI sentinel locally, with no fetch at all", async () => {
    const fetchSpy = vi.fn();
    globalThis.fetch = fetchSpy as unknown as typeof fetch;

    const style = await resolveStyle({ ...baseConfig, basemapLight: ESRI_SENTINEL }, "light");
    expect(style.name).toBe(ESRI_SENTINEL);
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it("fetches a curated URL and returns the fetched style on success", async () => {
    const remoteStyle = { version: 8, name: "fetched", sources: {}, layers: [] };
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve(remoteStyle),
    }) as unknown as typeof fetch;

    const style = await resolveStyle(baseConfig, "light");
    expect(style).toEqual(remoteStyle);
  });

  it("falls back to blankStyle when the fetch fails outright", async () => {
    globalThis.fetch = vi
      .fn()
      .mockRejectedValue(new Error("network down")) as unknown as typeof fetch;
    vi.spyOn(console, "warn").mockImplementation(() => {});

    const style = await resolveStyle(baseConfig, "light");
    expect(style.name).toContain("fallback");
    expect(style.sources).toEqual({});
  });

  it("falls back to blankStyle when the response is not ok", async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 404,
      statusText: "Not Found",
    }) as unknown as typeof fetch;
    vi.spyOn(console, "warn").mockImplementation(() => {});

    const style = await resolveStyle(baseConfig, "light");
    expect(style.name).toContain("fallback");
  });

  it("resolves the dark theme's own basemap value, not the light one", async () => {
    const fetchSpy = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ version: 8, name: "dark-fetched", sources: {}, layers: [] }),
    });
    globalThis.fetch = fetchSpy as unknown as typeof fetch;

    await resolveStyle(
      { ...baseConfig, basemapLight: ESRI_SENTINEL, basemapDark: CARTO_VOYAGER_URL },
      "dark",
    );
    expect(fetchSpy).toHaveBeenCalledWith(CARTO_VOYAGER_URL);
  });

  it("fetches vaCentral's own curated URL like any other curated URL", async () => {
    const remoteStyle = { version: 8, name: "vacentral-fetched", sources: {}, layers: [] };
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve(remoteStyle),
    }) as unknown as typeof fetch;

    const style = await resolveStyle({ ...baseConfig, basemapLight: VACENTRAL_LIGHT_URL }, "light");
    expect(style).toEqual(remoteStyle);
  });

  it("labels vaCentral's fallback style 'curated', not 'custom' — it is a curated URL, not an operator style", async () => {
    globalThis.fetch = vi
      .fn()
      .mockRejectedValue(new Error("network down")) as unknown as typeof fetch;
    vi.spyOn(console, "warn").mockImplementation(() => {});

    const style = await resolveStyle({ ...baseConfig, basemapLight: VACENTRAL_LIGHT_URL }, "light");
    expect(style.name).toBe("curated-fallback");
  });

  it("treats a value that isn't a curated URL or the ESRI sentinel as the operator's custom style, fetched as-is", async () => {
    const customUrl = "https://tiles.example.com/style.json";
    const fetchSpy = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ version: 8, name: "custom", sources: {}, layers: [] }),
    });
    globalThis.fetch = fetchSpy as unknown as typeof fetch;

    await resolveStyle(
      {
        ...baseConfig,
        basemapLight: customUrl,
        customStyleUrl: customUrl,
        customStyleApiKey: "secret",
      },
      "light",
    );
    // Fetched with the URL exactly as given — no `?key=` or similar injected from customStyleApiKey.
    expect(fetchSpy).toHaveBeenCalledWith(customUrl);
  });
});
