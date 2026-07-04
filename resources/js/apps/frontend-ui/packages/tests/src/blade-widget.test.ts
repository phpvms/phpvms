import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import BladeWidget from "@/components/widgets/BladeWidget.vue";

function htmlResponse(html: string, ok = true, status = 200): Partial<Response> {
  return { ok, status, text: async () => html };
}

beforeEach(() => {
  document.head.innerHTML = '<meta name="csrf-token" content="tok123">';
});

afterEach(() => {
  vi.unstubAllGlobals();
  document.head.innerHTML = "";
});

describe("BladeWidget (island)", () => {
  it("injects fetched HTML into the container", async () => {
    const fetchMock = vi.fn(async () => htmlResponse('<p class="inner">hello</p>'));
    vi.stubGlobal("fetch", fetchMock as never);

    const w = mount(BladeWidget, { props: { endpoint: "/ext/foo" } });
    // loading state first
    expect(w.find('[role="status"]').exists()).toBe(true);
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(url).toBe("/ext/foo");
    expect((init.headers as Record<string, string>)["X-Requested-With"]).toBe("XMLHttpRequest");
    expect(w.find(".inner").text()).toBe("hello");
  });

  it("shows a fail-visible error on a non-OK response", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => htmlResponse("", false, 500)) as never);
    const w = mount(BladeWidget, { props: { endpoint: "/ext/foo" } });
    await flushPromises();
    const err = w.find("[data-blade-error]");
    expect(err.exists()).toBe(true);
    expect(err.text()).toContain("500");
  });

  it("intercepts a form submit and re-fetches with the CSRF header", async () => {
    const initialHtml =
      '<form method="post" action="/ext/foo/submit"><input name="q" value="hi"></form>';
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(htmlResponse(initialHtml))
      .mockResolvedValueOnce(htmlResponse('<p class="done">saved</p>'));
    vi.stubGlobal("fetch", fetchMock as never);

    const w = mount(BladeWidget, { props: { endpoint: "/ext/foo" }, attachTo: document.body });
    await flushPromises();
    expect(w.find("form").exists()).toBe(true);

    // Submit the injected form.
    await w.find("form").trigger("submit");
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledTimes(2);
    const [url, init] = fetchMock.mock.calls[1] as unknown as [string, RequestInit];
    expect(url).toBe("/ext/foo/submit");
    expect(init.method).toBe("POST");
    const headers = init.headers as Record<string, string>;
    expect(headers["X-CSRF-TOKEN"]).toBe("tok123");
    // Body carries the serialized form field.
    expect(String(init.body)).toContain("q=hi");
    // Container swapped to the submit response.
    expect(w.find(".done").text()).toBe("saved");

    w.unmount();
  });
});
