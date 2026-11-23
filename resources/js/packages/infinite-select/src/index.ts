// The package entry is the "complete" build, which registers virtual_scroll.
import TomSelect from "tom-select";

export type InfiniteSelectOption = { value: string | number; label: string };

export type InfiniteSelectPage = { options: InfiniteSelectOption[]; hasMore: boolean };

export type InfiniteSelectOptions = {
  getPage(search: string, page: number): Promise<InfiniteSelectPage>;
  placeholder?: string;
  initial?: InfiniteSelectOption | null;
  onChange(value: string | null): void;
  resolveLabel?(value: string): Promise<string | null>;
  onError?(error: unknown): void;
};

export type InfiniteSelectHandle = {
  setValue(value: string | null): void;
  destroy(): void;
};

export function createInfiniteSelect(
  el: HTMLSelectElement,
  opts: InfiniteSelectOptions,
): InfiniteSelectHandle {
  const { getPage, placeholder, initial, onChange, resolveLabel, onError } = opts;
  const options = initial ? [{ value: String(initial.value), label: initial.label }] : [];

  let destroyed = false;
  let inInput = false;
  let nextLoadFromInput = false;

  let tom: any = new TomSelect(el, {
    plugins: ["virtual_scroll"],
    valueField: "value",
    labelField: "label",
    searchField: [],
    maxItems: 1,
    maxOptions: null,
    placeholder,
    preload: "focus",
    loadThrottle: 300,
    options,
    items: options.map((option) => option.value),
    // Server does the filtering; do not re-score locally.
    score: () => () => 1,
    firstUrl: () => 1,
    shouldLoad: () => true,
    load(this: any, query: string, callback: (options?: InfiniteSelectOption[]) => void) {
      const self = this;
      const page = self.getUrl(query);
      // For a load that never reaches the plugin's callback.
      const settleLoad = () => {
        self.loading = Math.max(self.loading - 1, 0);

        if (self.loading === 0) {
          self.wrapper.classList.remove(
            ...String(self.settings.loadingClass).split(/\s+/).filter(Boolean),
          );
        }
      };

      const fromInput = nextLoadFromInput;
      nextLoadFromInput = false;

      // Typing back to a query already on screen must not replace page 1
      // with its queued page 2.
      if (fromInput && page > 1) {
        self.setNextUrl(query, page);
        settleLoad();

        // Nothing else re-renders, so the loading spinner would linger.
        self.refreshOptions(false);
        return;
      }

      getPage(query, page)
        .then((response) => {
          if (destroyed) {
            return;
          }

          // The plugin's append mode can be stuck on from a dropped scroll
          // load; page 1 must still replace. Stale ones are discarded below.
          if (page === 1 && query === self.lastValue) {
            self.clearOptions((_option: unknown, value: string) => self.items.includes(value));
          }

          // Before the callback: it may request the next page at once, and a
          // discarded response resets this cursor itself.
          if (response.hasMore) {
            self.setNextUrl(query, page + 1);
          }

          callback(response.options);
        })
        .catch((error) => {
          if (destroyed) {
            return;
          }

          if (query !== self.lastValue) {
            // The plugin discards a response for a query that was left.
            callback([]);
            return;
          }

          if (page > 1) {
            // An empty append settles the plugin; re-queue after it, or its
            // post-callback check would retry the failed page in a loop.
            callback([]);
            self.setNextUrl(query, page);
            self.refreshOptions(false);
          } else {
            // The plugin's callback would snapshot an empty preload, so
            // settle by hand and drop the previous query's rows.
            settleLoad();
            self.clearOptions((_option: unknown, value: string) => self.items.includes(value));

            if (query === "") {
              // Let refocusing retry the preload.
              self.wrapper.classList.remove("preloaded");
            }
          }

          onError?.(error);
        });
    },
    onChange(value: string) {
      onChange(value === "" ? null : value);
    },
  } as any);

  // Tell input-driven loads from scroll-driven ones: only the input path
  // calls load() from inside _onInput.
  tom.hook("before", "_onInput", () => {
    inInput = true;
  });
  tom.hook("after", "_onInput", () => {
    inInput = false;
  });
  // Only a load that will run may set the flag, or a refused call could
  // clear it while an input-driven load is still debounced.
  tom.hook("before", "load", (value: string) => {
    if (tom.canLoad(value)) {
      nextLoadFromInput = inInput;
    }
  });

  return {
    setValue(value) {
      if (!tom) {
        return;
      }

      if (value === null || value === undefined || value === "") {
        tom.clear(true);
        return;
      }

      const key = String(value);

      const known = !!tom.options[key];

      if (!known) {
        tom.addOption({ value: key, label: key });
      }

      tom.setValue(key, true);

      if (!known && resolveLabel) {
        resolveLabel(key)
          .then((label) => {
            if (tom && label && label.trim() !== "" && tom.items.includes(key)) {
              tom.updateOption(key, { value: key, label });
            }
          })
          .catch(() => {});
      }
    },
    destroy() {
      destroyed = true;
      tom?.destroy();
      tom = null;
    },
  };
}
