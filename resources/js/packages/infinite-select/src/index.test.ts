import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createInfiniteSelect, type InfiniteSelectPage } from "./index.ts";

type Deferred = {
  query: string;
  page: number;
  resolve(page: InfiniteSelectPage): void;
  reject(error: unknown): void;
};

const opt = (value: string) => ({ value, label: value });

function setup(
  initial?: { value: string; label: string },
  resolveLabel?: (value: string) => Promise<string | null>,
) {
  const calls: Deferred[] = [];
  const getPage = vi.fn((query: string, page: number) => {
    return new Promise<InfiniteSelectPage>((resolve, reject) => {
      calls.push({ query, page, resolve, reject });
    });
  });
  const onChange = vi.fn();
  const onError = vi.fn();
  const el = document.createElement("select");
  document.body.appendChild(el);
  const handle = createInfiniteSelect(el, { getPage, onChange, onError, initial, resolveLabel });
  const tom = (el as any).tomselect;

  // Past tom-select's refresh and load throttles, then flush the promise chain.
  const settle = () => vi.advanceTimersByTimeAsync(700);

  const type = async (value: string) => {
    tom.control_input.value = value;
    tom.control_input.dispatchEvent(new Event("input"));
    await settle();
  };

  const last = () => calls[calls.length - 1];
  const args = () => getPage.mock.calls.map(([q, p]) => [q, p]);
  const optionValues = () => Object.keys(tom.options).sort();

  return {
    calls,
    getPage,
    onChange,
    onError,
    el,
    handle,
    tom,
    settle,
    type,
    last,
    args,
    optionValues,
  };
}

describe("createInfiniteSelect", () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = "";
  });

  it("ignores a rejected page response after destroy", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();

    t.handle.destroy();
    t.last().reject(new Error("late"));
    await t.settle();

    expect(t.onError).not.toHaveBeenCalled();
    expect(t.onChange).not.toHaveBeenCalled();
  });

  it("ignores a resolved page response after destroy", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.tom.settings.shouldLoadMore = () => true;

    t.handle.destroy();
    t.last().resolve({ options: [opt("z1")], hasMore: true });
    await t.settle();

    expect(t.args()).toEqual([["", 1]]);
    expect(t.optionValues()).toEqual([]);
    expect(t.onError).not.toHaveBeenCalled();
    expect(t.onChange).not.toHaveBeenCalled();
  });

  it("never fetches a queued page 2 when typing back to its query", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("x1"), opt("x2")], hasMore: true });
    await t.settle();

    // "a" is queued behind the load debounce, so "" still holds its page-2 cursor.
    t.tom.control_input.value = "a";
    t.tom.control_input.dispatchEvent(new Event("input"));
    await vi.advanceTimersByTimeAsync(350);
    expect(t.getPage).toHaveBeenCalledTimes(1);

    await t.type("");
    // The page-2 cursor is re-queued, not fetched: page 1 stays on screen.
    expect(t.args()).toEqual([["", 1]]);
    expect(t.optionValues()).toEqual(["x1", "x2"]);
    expect(t.tom.loading).toBe(0);
  });

  it("removes the loading spinner when typing back to the query on screen", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("x1"), opt("x2")], hasMore: true });
    await t.settle();

    t.tom.control_input.value = "a";
    t.tom.control_input.dispatchEvent(new Event("input"));
    await vi.advanceTimersByTimeAsync(350);
    await t.type("");

    expect(t.tom.dropdown_content.querySelector(".spinner")).toBeNull();
  });

  it("still treats a debounced load as typed back when another load call is refused", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("x1"), opt("x2")], hasMore: true });
    await t.settle();

    t.tom.control_input.value = "a";
    t.tom.control_input.dispatchEvent(new Event("input"));
    await vi.advanceTimersByTimeAsync(350);

    t.tom.control_input.value = "";
    t.tom.control_input.dispatchEvent(new Event("input"));
    await vi.advanceTimersByTimeAsync(100);
    t.tom.loadedSearches.refused = true;
    t.tom.load("refused");
    await t.settle();

    expect(t.args()).toEqual([["", 1]]);
    expect(t.optionValues()).toEqual(["x1", "x2"]);
  });

  it("keeps the page-2 cursor when typing back so a scroll fetches it", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("x1"), opt("x2")], hasMore: true });
    await t.settle();

    t.tom.control_input.value = "a";
    t.tom.control_input.dispatchEvent(new Event("input"));
    await vi.advanceTimersByTimeAsync(350);
    await t.type("");

    t.tom.settings.shouldLoadMore = () => true;
    t.tom.dropdown_content.dispatchEvent(new Event("scroll"));
    await t.settle();

    expect(t.args()).toEqual([
      ["", 1],
      ["", 2],
    ]);
  });

  it("does not let a stale page 2 of the old query leak into a new query's results", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("x1"), opt("x2")], hasMore: true });
    await t.settle();

    t.tom.settings.shouldLoadMore = () => true;
    t.tom.dropdown_content.dispatchEvent(new Event("scroll"));
    await t.settle();
    const stalePage2 = t.last();
    expect([stalePage2.query, stalePage2.page]).toEqual(["", 2]);

    await t.type("a");
    const a1 = t.last();
    expect([a1.query, a1.page]).toEqual(["a", 1]);

    a1.resolve({ options: [opt("a1")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual(["a1"]);

    stalePage2.resolve({ options: [opt("x2")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual(["a1"]);
    expect(t.tom.loading).toBe(0);
  });

  it("never shows a response for a query the user has left, and refetches it on return", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [], hasMore: false });
    await t.settle();

    await t.type("a");
    const a1 = t.last();
    await t.type("ab");
    const ab1 = t.last();
    expect([a1.query, ab1.query]).toEqual(["a", "ab"]);

    a1.resolve({ options: [opt("a-old")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual([]);
    expect(t.tom.loading).toBe(1);

    await t.type("a");
    expect(t.args()).toEqual([
      ["", 1],
      ["a", 1],
      ["ab", 1],
      ["a", 1],
    ]);

    ab1.resolve({ options: [opt("ab1")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual([]);

    t.last().resolve({ options: [opt("a1")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual(["a1"]);
    expect(t.tom.loading).toBe(0);
  });

  it("keeps the rows on screen when a response for a left query arrives late", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [], hasMore: false });
    await t.settle();

    await t.type("a");
    const a1 = t.last();
    await t.type("ab");
    t.last().resolve({ options: [opt("ab1")], hasMore: false });
    await t.settle();

    a1.resolve({ options: [opt("a-old")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual(["ab1"]);
    expect(t.tom.loading).toBe(0);
  });

  it("settles quietly when a left query's request fails, and refetches it on return", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [], hasMore: false });
    await t.settle();

    await t.type("a");
    const a1 = t.last();
    await t.type("ab");
    a1.reject(new Error("late"));
    await t.settle();
    expect(t.onError).not.toHaveBeenCalled();
    expect(t.tom.loading).toBe(1);

    t.last().resolve({ options: [opt("ab1")], hasMore: false });
    await t.settle();
    expect(t.tom.loading).toBe(0);

    await t.type("a");
    expect(t.last().query).toBe("a");
    expect(t.getPage).toHaveBeenCalledTimes(4);
  });

  it("clears the previous query's options and keeps the selection when page 1 fails", async () => {
    const t = setup(opt("sel"));
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("x1")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual(["sel", "x1"]);

    await t.type("a");
    const err = new Error("boom");
    t.last().reject(err);
    await t.settle();

    expect(t.optionValues()).toEqual(["sel"]);
    expect(t.tom.dropdown_content.querySelector('[data-value="x1"]')).toBeNull();
    expect(t.tom.loading).toBe(0);
    expect(t.tom.items).toEqual(["sel"]);
    expect(t.onError).toHaveBeenCalledTimes(1);
    expect(t.onError).toHaveBeenCalledWith(err);
  });

  it("retries the preload on refocus after the empty query fails", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().reject(new Error("boom"));
    await t.settle();
    expect(t.onError).toHaveBeenCalledTimes(1);

    t.tom.blur();
    await t.settle();
    t.tom.focus();
    await t.settle();

    expect(t.args()).toEqual([
      ["", 1],
      ["", 1],
    ]);
  });

  it("requests page 2 on scroll and appends it", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("p1a"), opt("p1b")], hasMore: true });
    await t.settle();
    expect(t.args()).toEqual([["", 1]]);

    // happy-dom has no layout, so force the plugin's "near the bottom" check.
    t.tom.settings.shouldLoadMore = () => true;
    t.tom.dropdown_content.dispatchEvent(new Event("scroll"));
    await t.settle();
    expect(t.args()).toEqual([
      ["", 1],
      ["", 2],
    ]);

    t.last().resolve({ options: [opt("p2a")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual(["p1a", "p1b", "p2a"]);
  });

  it("fetches the next page when a page is too short to scroll", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.tom.settings.shouldLoadMore = () => true;

    t.last().resolve({ options: [opt("p1a")], hasMore: true });
    await t.settle();
    expect(t.args()).toEqual([
      ["", 1],
      ["", 2],
    ]);

    t.last().resolve({ options: [opt("p2a")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual(["p1a", "p2a"]);
  });

  it("reports a failed page 2 once and retries it only on the next scroll", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("p1a")], hasMore: true });
    await t.settle();

    t.tom.settings.shouldLoadMore = () => true;
    t.tom.dropdown_content.dispatchEvent(new Event("scroll"));
    await t.settle();
    t.last().reject(new Error("boom"));
    await t.settle();

    expect(t.onError).toHaveBeenCalledTimes(1);
    expect(t.args()).toEqual([
      ["", 1],
      ["", 2],
    ]);
    expect(t.optionValues()).toEqual(["p1a"]);
    expect(t.tom.loading).toBe(0);
    expect(t.tom.dropdown_content.querySelector(".loading-more-results")).not.toBeNull();

    t.tom.dropdown_content.dispatchEvent(new Event("scroll"));
    await t.settle();
    expect(t.args()).toEqual([
      ["", 1],
      ["", 2],
      ["", 2],
    ]);
  });

  it("reports onChange with null for a cleared selection", async () => {
    const t = setup();
    t.tom.addOption(opt("x"));
    t.tom.addItem("x");
    expect(t.onChange).toHaveBeenLastCalledWith("x");

    t.tom.clear();
    expect(t.onChange).toHaveBeenLastCalledWith(null);
  });

  it("setValue selects silently, resolves the label of an unknown value, and clears", async () => {
    const resolveLabel = vi.fn(async (_value: string) => "Resolved");
    const t = setup(undefined, resolveLabel);

    t.handle.setValue("7");
    await t.settle();
    expect(t.tom.items).toEqual(["7"]);
    expect(t.tom.options["7"].label).toBe("Resolved");
    expect(resolveLabel).toHaveBeenCalledWith("7");

    t.handle.setValue(null);
    expect(t.tom.items).toEqual([]);
    expect(t.onChange).not.toHaveBeenCalled();
  });

  it("setValue does not resolve a label it already has", async () => {
    const resolveLabel = vi.fn(async (_value: string) => "Other");
    const t = setup(opt("sel"), resolveLabel);

    t.handle.setValue("sel");
    await t.settle();

    expect(resolveLabel).not.toHaveBeenCalled();
    expect(t.tom.options.sel.label).toBe("sel");
  });
});
