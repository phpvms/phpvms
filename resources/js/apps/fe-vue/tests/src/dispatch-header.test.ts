import { flushPromises, mount } from "@vue/test-utils";
import { defineComponent, nextTick, ref } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import AppHeader from "@/app/shell/AppHeader.vue";
import HeaderClocks from "@/app/shell/HeaderClocks.vue";
import HeaderDuty from "@/app/shell/HeaderDuty.vue";
import HeaderMetar from "@/app/shell/HeaderMetar.vue";
import HeaderSector from "@/app/shell/HeaderSector.vue";
import HeaderStatusDrawer from "@/app/shell/HeaderStatusDrawer.vue";
import { useHeaderClocks } from "@/app/shell/useHeaderClocks";
import { useHeaderTheme } from "@/app/shell/useHeaderTheme";
import { useMetar } from "@/app/shell/useMetar";
import type { HeaderUser, MetarResponse, WeatherStation } from "@/app/shell/headerTypes";

const inertia = vi.hoisted(() => ({
  props: {} as Record<string, unknown>,
  visits: [] as string[],
}));

vi.mock("@inertiajs/vue3", async () => {
  const { defineComponent, h } = await import("vue");

  return {
    Link: defineComponent({
      props: { href: { type: String, required: true } },
      setup:
        (props, { slots }) =>
        () =>
          h("a", { href: props.href }, slots.default?.()),
    }),
    router: { visit: (href: string) => inertia.visits.push(href) },
    usePage: () => ({ props: inertia.props }),
  };
});

const user: HeaderUser = {
  id: 1,
  name: "Taylor Swift",
  avatar: null,
  ident: "PVA123",
  callsign: "TAYLOR1",
  airline: { name: "phpVMS Air", icao: "PVA", iata: "PV", logo: null },
};

const buttonStub = defineComponent({ template: "<button><slot /></button>" });
const menuStub = defineComponent({
  props: { items: { type: Array, default: () => [] } },
  template: "<div><slot /></div>",
});
const drawerStub = defineComponent({
  props: { open: { type: Boolean, required: true } },
  template: '<div :data-open="open"><slot name="content" /></div>',
});
let storage = new Map<string, string>();

beforeEach(() => {
  inertia.props = {};
  inertia.visits = [];
  storage = new Map();
  vi.stubGlobal("localStorage", {
    getItem: (key: string) => storage.get(key) ?? null,
    setItem: (key: string, value: string) => storage.set(key, value),
    clear: () => storage.clear(),
  });
});

afterEach(() => {
  vi.useRealTimers();
  document.documentElement.className = "";
  document.documentElement.dataset.themeMode = "";
  localStorage.clear();
  vi.unstubAllGlobals();
});

describe("dispatch header status", () => {
  it("renders current, loading, missing, stale, unavailable, and no-station METAR states", async () => {
    const weather: MetarResponse = {
      icao: "KORD",
      metar: "KORD 121651Z 18012KT 10SM CLR 22/10 A2992",
      observedAt: "2026-08-12T16:51:00Z",
      isStale: false,
    };
    const global = { stubs: { UButton: buttonStub } };
    expect(
      mount(HeaderMetar, { props: { state: { kind: "loading", station: "KORD" } }, global }).text(),
    ).toContain("Loading METAR");
    expect(
      mount(HeaderMetar, { props: { state: { kind: "missing-station" } }, global }).text(),
    ).toContain("No station");
    expect(
      mount(HeaderMetar, { props: { state: { kind: "missing", station: "KORD" } }, global }).text(),
    ).toContain("No METAR");
    expect(
      mount(HeaderMetar, { props: { state: { kind: "loaded", weather } }, global }).text(),
    ).toContain(weather.metar);
    expect(
      mount(HeaderMetar, {
        props: { state: { kind: "stale", weather: { ...weather, isStale: true } } },
        global,
      }).text(),
    ).toContain("Stale");
    const error = mount(HeaderMetar, {
      props: { state: { kind: "error", station: "KORD" } },
      global: { stubs: { UButton: buttonStub } },
    });
    expect(error.text()).toContain("METAR unavailable");
    await error.get("button").trigger("click");
    expect(error.emitted("retry")).toHaveLength(1);
  });

  it("shows active sectors as Inertia links and has an explicit no-sector state", async () => {
    const active = mount(HeaderSector, {
      props: {
        sector: {
          pirepId: "p-1",
          ident: "PVA123",
          departureIcao: "KORD",
          arrivalIcao: "KJFK",
          state: "in_progress",
        },
      },
      global: { stubs: { Link: { props: ["href"], template: '<a :href="href"><slot /></a>' } } },
    });

    expect(active.get("a").attributes("href")).toBe("/pireps/p-1");
    expect(active.text()).toContain("KORD → KJFK");
    await active.setProps({
      sector: {
        pirepId: "p-1",
        ident: "PVA123",
        departureIcao: "KORD",
        arrivalIcao: "KJFK",
        state: "paused",
      },
    });
    expect(active.text()).toContain("KORD → KJFK");
    expect(mount(HeaderSector, { props: { sector: null } }).text()).toContain("No active sector");
  });

  it("renders readable duty labels and leaves clocks available when local time is not", () => {
    for (const duty of [
      { state: "on_duty", label: "On duty", color: "success" },
      { state: "paused", label: "Paused", color: "warning" },
      { state: "off_duty", label: "Off duty", color: "neutral" },
    ] as const) {
      const wrapper = mount(HeaderDuty, { props: { duty } });
      expect(wrapper.text()).toBe(duty.label);
      expect(wrapper.classes()).toContain(`is-${duty.color}`);
    }
    const clocks = mount(HeaderClocks, { props: { utc: "16:51Z", local: null, timezone: null } });
    expect(clocks.text()).toContain("16:51Z");
    expect(clocks.text()).toContain("Unavailable");
    expect(clocks.findAll("time")).toHaveLength(2);
  });

  it("keeps full status information in the header-owned mobile drawer", async () => {
    const wrapper = mount(HeaderStatusDrawer, {
      props: {
        duty: { state: "on_duty", label: "On duty", color: "success" },
        local: "11:51",
        metar: { kind: "missing", station: "KORD" },
        open: false,
        sector: {
          pirepId: "p-1",
          ident: "PVA123",
          departureIcao: "KORD",
          arrivalIcao: "KJFK",
          state: "in_progress",
        },
        timezone: "America/Chicago",
        user,
        utc: "16:51Z",
      },
      global: {
        stubs: {
          UDrawer: drawerStub,
          UButton: buttonStub,
          Link: { props: ["href"], template: '<a :href="href"><slot /></a>' },
        },
      },
    });

    expect(wrapper.get("[data-open]").attributes("data-open")).toBe("false");
    await wrapper.setProps({ open: true });
    expect(wrapper.get("[data-open]").attributes("data-open")).toBe("true");
    expect(wrapper.text()).toContain("On duty");
    expect(wrapper.text()).toContain("No METAR");
    expect(wrapper.text()).toContain("Taylor Swift");
  });

  it("keeps desktop, tablet, and mobile header information in one persistent composition", async () => {
    inertia.props = {
      appName: "phpVMS",
      auth: { user },
      pilotChrome: {
        activeSector: {
          pirepId: "p-1",
          ident: "PVA123",
          departureIcao: "KORD",
          arrivalIcao: "KJFK",
          state: "in_progress",
        },
        duty: { state: "on_duty", label: "On duty", color: "success" },
        station: null,
      },
    };
    vi.stubGlobal("matchMedia", () => ({
      matches: false,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }));

    const wrapper = mount(AppHeader, {
      global: {
        stubs: {
          UButton: buttonStub,
          UDropdownMenu: menuStub,
          UDrawer: drawerStub,
        },
      },
    });

    expect(wrapper.get(".primary-row").text()).toContain("phpVMS Air");
    expect(wrapper.get(".mobile-sector").text()).toContain("KORD → KJFK");
    expect(wrapper.get(".mobile-utc time").element.tagName).toBe("TIME");
    expect(wrapper.get(".operations-row").text()).toContain("On duty");
    expect(wrapper.get(".operations-row").text()).toContain("No station");
    expect(wrapper.get(".operations-row").text()).toContain("Unavailable");
    expect(wrapper.get("[data-open]").attributes("data-open")).toBe("false");

    await wrapper.get(".pv-header-status-trigger").trigger("click");
    expect(wrapper.get("[data-open]").attributes("data-open")).toBe("true");
    expect(wrapper.get(".pv-header-status-drawer").text()).toContain("Taylor Swift");
    expect(wrapper.get(".pv-header-status-drawer").text()).toContain("On duty");
  });
});

describe("dispatch header composables", () => {
  it("loads METAR after mount, treats empty data as missing, and cancels station changes", async () => {
    let firstSignal: AbortSignal | undefined;
    const first = new Promise<Response>(() => undefined);
    const weather: MetarResponse = { icao: "KJFK", metar: null, observedAt: null, isStale: false };
    vi.stubGlobal(
      "fetch",
      vi.fn((_: string, init: RequestInit) => {
        if (!firstSignal) {
          firstSignal = init.signal as AbortSignal;
          return first;
        }
        return Promise.resolve(new Response(JSON.stringify(weather), { status: 200 }));
      }),
    );

    const station = ref<WeatherStation | null>({ icao: "KORD", timezone: "America/Chicago" });
    const Host = defineComponent({ setup: () => useMetar(station), template: "<div />" });
    const wrapper = mount(Host);
    await nextTick();
    expect(wrapper.vm.state.kind).toBe("loading");

    station.value = { icao: "KJFK", timezone: "America/New_York" };
    await flushPromises();

    expect(firstSignal?.aborted).toBe(true);
    expect(wrapper.vm.state.kind).toBe("missing");
  });

  it("keeps light, dark, and auto separate while auto follows system changes", async () => {
    let listener: ((event: MediaQueryListEvent) => void) | undefined;
    vi.stubGlobal(
      "matchMedia",
      vi.fn(() => ({
        matches: false,
        addEventListener: (_: string, callback: (event: MediaQueryListEvent) => void) => {
          listener = callback;
        },
        removeEventListener: vi.fn(),
      })),
    );

    const Host = defineComponent({ setup: useHeaderTheme, template: "<div />" });
    const wrapper = mount(Host);
    await nextTick();

    wrapper.vm.select("light");
    expect(storage.get("skylight.theme")).toBe("light");
    expect(document.documentElement.classList.contains("dark")).toBe(false);

    wrapper.vm.select("dark");
    expect(storage.get("skylight.theme")).toBe("dark");
    expect(document.documentElement.classList.contains("dark")).toBe(true);

    wrapper.vm.select("auto");
    listener?.({ matches: true } as MediaQueryListEvent);
    expect(storage.get("skylight.theme")).toBe("auto");
    expect(document.documentElement.classList.contains("dark")).toBe(true);
  });

  it("updates UTC and station-local clocks at the next minute boundary", async () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2026-08-12T16:51:30.000Z"));
    const station = ref<WeatherStation | null>({ icao: "KORD", timezone: "America/Chicago" });
    const Host = defineComponent({ setup: () => useHeaderClocks(station), template: "<div />" });
    const wrapper = mount(Host);
    await nextTick();

    expect(wrapper.vm.utc).toBe("16:51Z");
    expect(wrapper.vm.local).not.toBeNull();
    await vi.advanceTimersByTimeAsync(30_025);
    expect(wrapper.vm.utc).toBe("16:52Z");

    wrapper.unmount();
  });
});
