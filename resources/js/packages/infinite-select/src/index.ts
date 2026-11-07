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

  let generation = 0;
  let loadingMore = false;

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

      if (page === 1) {
        generation++;
      }

      const gen = generation;

      if (page > 1) {
        loadingMore = true;
      }

      const isStale = () => gen !== generation || query !== self.lastValue;

      // Finish a load whose result is not passed through the real callback.
      const settle = () => {
        if (loadingMore) {
          // The virtual_scroll plugin is in append mode: an empty
          // callback appends nothing and resets its loading_more flag.
          loadingMore = false;
          self.loadCallback([]);
          return;
        }

        self.loading = Math.max(self.loading - 1, 0);

        if (self.loading === 0) {
          self.wrapper.classList.remove(
            ...String(self.settings.loadingClass).split(/\s+/).filter(Boolean),
          );
        }
      };

      getPage(query, page)
        .then((response) => {
          if (isStale()) {
            delete self.loadedSearches[query];
            settle();
            return;
          }

          if (page === 1) {
            // Results replace, never append, even if the plugin's
            // loading_more flag is stuck true.
            self.clearOptions((_option: unknown, value: string) => self.items.includes(value));
          }

          if (response.hasMore) {
            self.setNextUrl(query, page + 1);
          }

          loadingMore = false;
          callback(response.options);

          // A page that does not overflow the dropdown can never be
          // scrolled, so virtual_scroll never loads the next one.
          // Nudge its scroll listener until the list overflows.
          requestAnimationFrame(() => {
            const content = self.dropdown_content;

            if (
              self.isOpen &&
              content &&
              content.scrollHeight <= content.clientHeight &&
              content.querySelector(".loading-more-results")
            ) {
              content.dispatchEvent(new Event("scroll"));
            }
          });
        })
        .catch((error) => {
          const stale = isStale();

          delete self.loadedSearches[query];

          if (page > 1 && !stale) {
            // Let the next scroll retry the same page.
            self.setNextUrl(query, page);
          }

          settle();

          if (!stale) {
            onError?.(error);
          }
        });
    },
    onChange(value: string) {
      onChange(value === "" ? null : value);
    },
  } as any);

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
      tom?.destroy();
      tom = null;
    },
  };
}
