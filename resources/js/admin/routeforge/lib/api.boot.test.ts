/**
 * Vitest coverage for the boot loader path through `lib/api.ts::getBoot()`.
 *
 * happy-dom provides the `document` global. Each test seeds a fresh
 * `#routeforge-root` element with a `data-boot-url` attribute and stubs
 * `globalThis.fetch` to control the response. The `getBoot()` URL cache is
 * module-scoped, so the tests pin the same URL across cases — module state
 * is shared inside a single vitest worker.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { ApiError, getBoot } from "./api";

const BOOT_URL = "/admin/route-forge/api/boot";

beforeEach(() => {
  document.body.innerHTML = `<div id="routeforge-root" data-boot-url="${BOOT_URL}"></div>`;
});

afterEach(() => {
  vi.restoreAllMocks();
  document.body.innerHTML = "";
});

describe("getBoot", () => {
  it("resolves to the envelope on HTTP 200 (unwrapping the Laravel `data` key)", async () => {
    const envelope = {
      csrf_token: "token-abc",
      locale: "en",
      user: { id: 1, name: "Admin", can_commit: true },
      airlines: [],
      routes: {
        preview_airports: "/preview",
        subfleets: "/subfleets",
        airline_stats: "/stats",
        check_duplicates: "/dupes",
        lint: "/lint",
        commit: "/commit",
        bundles: "/bundles",
        bundle_edit_template: "/bundles/:id/edit",
      },
      config: {},
      translations: {},
    };
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ data: envelope }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );

    const result = await getBoot();
    expect(result).toEqual(envelope);
  });

  it("throws ApiError when the boot endpoint returns HTTP 500", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response('{"message":"Server error"}', {
        status: 500,
        statusText: "Internal Server Error",
        headers: { "Content-Type": "application/json" },
      }),
    );

    await expect(getBoot()).rejects.toBeInstanceOf(ApiError);
  });

  it("propagates network errors as plain Errors", async () => {
    vi.spyOn(globalThis, "fetch").mockRejectedValue(new TypeError("Network down"));

    await expect(getBoot()).rejects.toThrow("Network down");
  });
});
