import { mount } from "@vue/test-utils";
import { defineComponent, nextTick } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import PilotStatus from "@/app/shell/PilotStatus.vue";
import type { AppUser } from "@/app/shell/useAppChrome";

const inertia = vi.hoisted(() => ({ visits: [] as string[] }));

vi.mock("@inertiajs/vue3", () => ({
  router: { visit: (href: string) => inertia.visits.push(href) },
}));

const translations: Record<string, string> = {
  "common.profile": "Profile",
  "ui.sign_in": "Sign in",
  "ui.sign_out": "Sign out",
  "ui.theme": "Theme",
  "ui.theme_light": "Light",
  "ui.theme_dark": "Dark",
  "ui.theme_auto": "Auto",
};

vi.mock("laravel-vue-i18n", () => ({
  trans: (key: string) => translations[key] ?? key,
}));

const menuStub = defineComponent({
  props: { items: { type: Array, default: () => [] } },
  template: "<div><slot /></div>",
});

const user: AppUser = {
  id: 1,
  name: "Taylor Swift",
  avatar: null,
  ident: "PVA123",
  callsign: "TAYLOR1",
  airline: { name: "phpVMS Air", icao: "PVA", iata: "PV", logo: null },
};

type MenuItem = { label: string; type?: string; checked?: boolean; onSelect?: () => void };

function itemsOf(wrapper: ReturnType<typeof mount>): MenuItem[][] {
  return wrapper.getComponent(menuStub).props("items") as MenuItem[][];
}

let storage: Map<string, string>;

beforeEach(() => {
  inertia.visits = [];
  storage = new Map();
  vi.stubGlobal("localStorage", {
    getItem: (key: string) => storage.get(key) ?? null,
    setItem: (key: string, value: string) => storage.set(key, value),
    clear: () => storage.clear(),
  });
  vi.stubGlobal(
    "matchMedia",
    vi.fn(() => ({
      matches: false,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  );
});

afterEach(() => {
  document.documentElement.className = "";
  document.documentElement.dataset.themeMode = "";
  vi.unstubAllGlobals();
});

function mountStatus(currentUser: AppUser | null) {
  return mount(PilotStatus, {
    props: { initials: "TS", user: currentUser },
    global: {
      stubs: { UDropdownMenu: menuStub },
      mocks: { $t: (key: string) => translations[key] ?? key },
    },
  });
}

describe("sidebar account menu", () => {
  it("opens from a focusable trigger and lists identity, profile, sign out, and theme options", async () => {
    const wrapper = mountStatus(user);
    await nextTick();

    const trigger = wrapper.get(".pv-pilot-status");
    expect(trigger.element.tagName).toBe("BUTTON");
    expect(trigger.attributes("aria-label")).toBe("Account menu for Taylor Swift");

    const labels = itemsOf(wrapper)
      .flat()
      .map((item) => item.label);
    expect(labels).toEqual([
      "Taylor Swift",
      "PVA123 · TAYLOR1",
      "Profile",
      "Sign out",
      "Theme",
      "Light",
      "Dark",
      "Auto",
    ]);
  });

  it("visits profile and logout from the menu items", async () => {
    const wrapper = mountStatus(user);
    await nextTick();

    const groups = itemsOf(wrapper);
    const profile = groups.flat().find((item) => item.label === "Profile");
    const signOut = groups.flat().find((item) => item.label === "Sign out");
    profile?.onSelect?.();
    signOut?.onSelect?.();

    expect(inertia.visits).toEqual(["/profile", "/logout"]);
  });

  it("calls through to the theme setter when a theme option is selected", async () => {
    const wrapper = mountStatus(user);
    await nextTick();

    const dark = itemsOf(wrapper)
      .flat()
      .find((item) => item.label === "Dark");
    dark?.onSelect?.();

    expect(storage.get("skylight.theme")).toBe("dark");
    expect(document.documentElement.classList.contains("dark")).toBe(true);

    const light = itemsOf(wrapper)
      .flat()
      .find((item) => item.label === "Light");
    light?.onSelect?.();

    expect(storage.get("skylight.theme")).toBe("light");
    expect(document.documentElement.classList.contains("dark")).toBe(false);
  });

  it("strands neither sign-in nor the theme control when signed out", async () => {
    const wrapper = mountStatus(null);
    await nextTick();

    const labels = itemsOf(wrapper)
      .flat()
      .map((item) => item.label);
    expect(labels).toEqual(["Sign in", "Theme", "Light", "Dark", "Auto"]);

    const signIn = itemsOf(wrapper)
      .flat()
      .find((item) => item.label === "Sign in");
    signIn?.onSelect?.();
    expect(inertia.visits).toEqual(["/login"]);

    const auto = itemsOf(wrapper)
      .flat()
      .find((item) => item.label === "Auto");
    expect(auto?.checked).toBe(true);
  });
});
