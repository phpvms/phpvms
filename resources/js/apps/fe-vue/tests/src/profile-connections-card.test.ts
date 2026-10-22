import { mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import ProfileApiAccessCard from "@/components/profile/ProfileApiAccessCard.vue";
import ProfileConnectionsCard from "@/components/profile/ProfileConnectionsCard.vue";

// vi.hoisted: vi.mock is lifted above the file, so a plain top-level const is
// still in its temporal dead zone when the factory runs.
const { routerDelete, routerGet } = vi.hoisted(() => ({
  routerDelete: vi.fn(),
  routerGet: vi.fn(),
}));

vi.mock("@inertiajs/vue3", () => ({
  router: { delete: routerDelete, get: routerGet },
}));

function connection(
  overrides: Partial<App.Http.Data.ProfileConnectionData> = {},
): App.Http.Data.ProfileConnectionData {
  return {
    connectionId: "discord",
    displayName: "Discord",
    linked: false,
    linkable: true,
    providerUserId: null,
    ...overrides,
  };
}

beforeEach(() => {
  routerDelete.mockClear();
  routerGet.mockClear();
  vi.unstubAllGlobals();
});

describe("ProfileConnectionsCard", () => {
  it("renders nothing when no providers are offered", () => {
    const wrapper = mount(ProfileConnectionsCard, { props: { connections: [] } });

    expect(wrapper.find(".pv-profile-connections").exists()).toBe(false);
  });

  it("links an unlinked provider with a full page navigation, not an Inertia visit", () => {
    const wrapper = mount(ProfileConnectionsCard, { props: { connections: [connection()] } });
    const button = wrapper.findAll("ubutton").at(0);

    // The route 302s off-site; an XHR visit would follow it cross-origin and
    // die on CORS, so this must stay a plain href.
    expect(button?.attributes("href")).toBe("/oauth/discord/redirect?intent=link");
    expect(button?.attributes("to")).toBeUndefined();
  });

  it("unlinks a linked provider after confirmation", async () => {
    vi.stubGlobal("confirm", vi.fn().mockReturnValue(true));
    const wrapper = mount(ProfileConnectionsCard, {
      props: { connections: [connection({ linked: true, providerUserId: "9912" })] },
    });

    await wrapper.findAll("ubutton").at(0)?.trigger("click");

    expect(routerDelete).toHaveBeenCalledWith("/oauth/discord/unlink", { preserveScroll: true });
  });

  it("does not unlink when the confirmation is declined", async () => {
    vi.stubGlobal("confirm", vi.fn().mockReturnValue(false));
    const wrapper = mount(ProfileConnectionsCard, {
      props: { connections: [connection({ linked: true })] },
    });

    await wrapper.findAll("ubutton").at(0)?.trigger("click");

    expect(routerDelete).not.toHaveBeenCalled();
  });

  it("shows the provider account id the Blade page listed as Discord ID", () => {
    const wrapper = mount(ProfileConnectionsCard, {
      props: { connections: [connection({ linked: true, providerUserId: "9912" })] },
    });

    expect(wrapper.text()).toContain("9912");
  });
});

describe("ProfileApiAccessCard", () => {
  it("keeps the key hidden until it is revealed", async () => {
    const wrapper = mount(ProfileApiAccessCard, { props: { apiKey: "secret-key-value" } });

    expect(wrapper.text()).not.toContain("secret-key-value");

    const toggle = wrapper.findAll("ubutton").find((b) => b.text() === "Show");
    await toggle?.trigger("click");

    expect(wrapper.text()).toContain("secret-key-value");
  });

  it("regenerates only after confirmation", async () => {
    vi.stubGlobal("confirm", vi.fn().mockReturnValue(false));
    const wrapper = mount(ProfileApiAccessCard, { props: { apiKey: "k" } });

    const regen = wrapper.findAll("ubutton").find((b) => b.text().includes("Regenerate"));
    await regen?.trigger("click");
    expect(routerGet).not.toHaveBeenCalled();

    vi.stubGlobal("confirm", vi.fn().mockReturnValue(true));
    await regen?.trigger("click");
    expect(routerGet).toHaveBeenCalledWith("/profile/regen_apikey");
  });
});
