import { describe, it, expect, vi, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import WeatherWidget from "@/components/widgets/WeatherWidget.vue";

function mockFetch(impl: () => Promise<Partial<Response>>) {
  vi.stubGlobal("fetch", vi.fn(impl as never));
}

afterEach(() => vi.unstubAllGlobals());

describe("WeatherWidget", () => {
  it("shows loading immediately, then success data", async () => {
    let resolve!: (v: Partial<Response>) => void;
    mockFetch(() => new Promise((r) => (resolve = r)));
    const w = mount(WeatherWidget, { props: { icao: "KJFK" } });
    expect(w.find('[role="status"]').exists()).toBe(true);

    resolve({
      ok: true,
      json: async () => ({
        icao: "KJFK",
        metar: "KJFK 12345Z",
        conditions: "CLR",
        temperature: "15C",
        wind: "270/10",
        taf: null,
      }),
    });
    await flushPromises();

    expect(w.find('[role="status"]').exists()).toBe(false);
    expect(w.text()).toContain("CLR");
    expect(w.find(".metar").text()).toContain("KJFK 12345Z");
  });

  it("renders a fail-visible error when the API returns an error payload", async () => {
    mockFetch(async () => ({
      ok: false,
      status: 502,
      json: async () => ({ error: true, message: "provider down", icao: "KJFK" }),
    }));
    const w = mount(WeatherWidget, { props: { icao: "KJFK" } });
    await flushPromises();
    const err = w.find("[data-weather-error]");
    expect(err.exists()).toBe(true);
    expect(err.text()).toContain("provider down");
  });

  it("errors visibly when no ICAO is provided (does not fetch)", async () => {
    const spy = vi.fn();
    vi.stubGlobal("fetch", spy);
    const w = mount(WeatherWidget, { props: { icao: null } });
    await flushPromises();
    expect(spy).not.toHaveBeenCalled();
    expect(w.find("[data-weather-error]").exists()).toBe(true);
  });
});
