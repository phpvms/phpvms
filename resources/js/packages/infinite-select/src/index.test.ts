import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createInfiniteSelect, type InfiniteSelectPage } from "./index.ts";

type Deferred = {
  query: string;
  page: number;
  resolve(page: InfiniteSelectPage): void;
  reject(error: unknown): void;
};

const opt = (value: string) => ({ value, label: value });

function setup(initial?: { value: string; label: string }) {
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
  const handle = createInfiniteSelect(el, { getPage, onChange, onError, initial });
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

  it("ignores a response that settles after destroy", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    expect(t.args()).toEqual([["", 1]]);

    t.handle.destroy();
    t.last().reject(new Error("late"));
    await t.settle();

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

  it("does not fetch the next page from a frame queued before destroy", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [opt("z1")], hasMore: true });
    // Flush the .then only: its animation frame is queued but has not run.
    for (let i = 0; i < 5; i++) {
      await Promise.resolve();
    }

    t.handle.destroy();
    await t.settle();

    expect(t.args()).toEqual([["", 1]]);
  });

  it("does not fetch more after destroy when page 1 resolves late", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();

    // Passes if only the .then guard goes: the rAF guard still catches it.
    t.handle.destroy();
    t.last().resolve({ options: [opt("z1")], hasMore: true });
    await t.settle();

    expect(t.args()).toEqual([["", 1]]);
  });

  it("refetches the current query after its own response was discarded", async () => {
    const t = setup();
    t.tom.focus();
    await t.settle();
    t.last().resolve({ options: [], hasMore: false });
    await t.settle();

    await t.type("a");
    const first = t.last();
    expect(first.query).toBe("a");

    // "ab" supersedes "a" while "a" is still in flight.
    await t.type("ab");
    const second = t.last();
    expect(second.query).toBe("ab");

    // Back to "a" while its first request is pending: tom-select refuses to
    // load it again, so only the stale response can trigger the refetch.
    await t.type("a");
    expect(t.args()).toEqual([
      ["", 1],
      ["a", 1],
      ["ab", 1],
    ]);

    first.resolve({ options: [opt("old")], hasMore: false });
    await t.settle();
    expect(t.args()).toEqual([
      ["", 1],
      ["a", 1],
      ["ab", 1],
      ["a", 1],
    ]);
    expect(t.optionValues()).toEqual([]);

    second.resolve({ options: [opt("ab1")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual([]);

    const third = t.last();
    third.resolve({ options: [opt("a1")], hasMore: false });
    await t.settle();
    expect(t.optionValues()).toEqual(["a1"]);
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
});
