import { mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import ProfileEditCard from "@/components/profile/ProfileEditCard.vue";

const post = vi.fn();

/**
 * `useForm` is stubbed rather than exercised: the real one needs a live Inertia
 * page context. The stub keeps the parts this component actually reads --
 * indexable data, `errors`, `processing`, `post`, `reset`.
 */
vi.mock("@inertiajs/vue3", () => ({
  useForm: (data: Record<string, unknown>) => {
    const form = {
      ...data,
      errors: {} as Record<string, string>,
      processing: false,
      post,
      reset: vi.fn(),
    };
    return form;
  },
}));

function editData(
  overrides: Partial<App.Http.Data.ProfileEditData> = {},
): App.Http.Data.ProfileEditData {
  return {
    name: "Amelia Hart",
    email: "amelia@phpvms.net",
    airlineId: "1",
    homeAirport: { value: "12", label: "KJFK - John F Kennedy Intl" },
    country: "us",
    timezone: "America/New_York",
    simbriefUsername: "amelia",
    hubsOnly: false,
    airlines: [{ value: "1", label: "vms" }],
    countries: [{ value: "us", label: "United States" }],
    timezones: [{ value: "America/New_York", label: "(GMT/UTC-05:00) New York" }],
    fields: [],
    ...overrides,
  } as App.Http.Data.ProfileEditData;
}

function mountCard(overrides: Partial<App.Http.Data.ProfileEditData> = {}) {
  return mount(ProfileEditCard, {
    props: { edit: editData(overrides), profileId: 7 },
  });
}

beforeEach(() => {
  post.mockClear();
  vi.unstubAllGlobals();
});

describe("ProfileEditCard", () => {
  it("seeds the form from the server payload", () => {
    const wrapper = mountCard();
    const vm = wrapper.vm as unknown as { form: Record<string, unknown> };

    expect(vm.form.name).toBe("Amelia Hart");
    expect(vm.form.email).toBe("amelia@phpvms.net");
    expect(vm.form.home_airport_id).toBe("12");
    // Route is PUT|PATCH, but a file upload has to go over POST.
    expect(vm.form._method).toBe("put");
  });

  it("seeds each custom field under its field_{slug} input name", () => {
    const wrapper = mountCard({
      fields: [
        { slug: "vatsim_id", name: "VATSIM ID", value: "1234567", required: false },
        { slug: "discord", name: "Discord", value: null, required: true },
      ] as App.Http.Data.ProfileEditFieldData[],
    });
    const vm = wrapper.vm as unknown as { form: Record<string, unknown> };

    expect(vm.form.field_vatsim_id).toBe("1234567");
    // A never-saved field seeds empty, not null -- null would post as "null".
    expect(vm.form.field_discord).toBe("");
  });

  it("posts to the pilot's own profile url", async () => {
    const wrapper = mountCard();

    // The shared Nuxt UI stub renders UForm as a <uform> element, not a real
    // <form>, but it still forwards the @submit listener as a DOM handler.
    await wrapper.find("uform").trigger("submit");

    expect(post).toHaveBeenCalledOnce();
    expect(post.mock.calls[0]?.[0]).toBe("/profile/7");
    expect(post.mock.calls[0]?.[1]).toMatchObject({ forceFormData: true });
  });

  it("seeds the airport menu with the currently-selected airport", () => {
    const wrapper = mountCard();
    const vm = wrapper.vm as unknown as {
      airportItems: App.Http.Data.SelectOptionData[];
    };

    expect(vm.airportItems).toEqual([{ value: "12", label: "KJFK - John F Kennedy Intl" }]);
  });

  it("asks the search endpoint for hubs only when the setting is on", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: [{ id: 3, icao: "KLAX", name: "Los Angeles Intl" }] }),
    });
    vi.stubGlobal("fetch", fetchMock);
    vi.useFakeTimers();

    const wrapper = mountCard({ hubsOnly: true });
    const vm = wrapper.vm as unknown as { airportSearch: string };
    vm.airportSearch = "KLA";

    await vi.advanceTimersByTimeAsync(300);
    vi.useRealTimers();

    expect(fetchMock).toHaveBeenCalledOnce();
    const url = String(fetchMock.mock.calls[0]?.[0]);
    expect(url).toContain("search=KLA");
    expect(url).toContain("hubs=1");
  });

  it("does not query the endpoint for a single character", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    vi.useFakeTimers();

    const wrapper = mountCard();
    const vm = wrapper.vm as unknown as { airportSearch: string };
    vm.airportSearch = "K";

    await vi.advanceTimersByTimeAsync(300);
    vi.useRealTimers();

    expect(fetchMock).not.toHaveBeenCalled();
  });
});
