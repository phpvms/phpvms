/**
 * A searchable select that renders in place (skylight's USelectMenu), for
 * `<x-vms::ui.select-menu>`. TallStackUI's styled select teleports its list to
 * the body, outside a focus-trapped drawer, which takes the focus back from
 * its search box; this one stays inside the drawer.
 *
 * The options are rendered by Blade as `[role=option]` with `data-value`,
 * `data-search` and `aria-disabled`. Choosing one dispatches `select` with
 * `{ value }` from the root and closes the menu.
 */
interface Magics {
  $el: HTMLElement;
  $refs: { button: HTMLButtonElement; search: HTMLInputElement; list: HTMLElement; empty: HTMLElement };
  $dispatch(event: string, detail?: unknown): void;
  $nextTick(callback: () => void): void;
}

interface State {
  open: boolean;
  query: string;
  active: number;
}

interface Methods {
  options(): HTMLElement[];
  choices(): HTMLElement[];
  show(): void;
  close(refocus?: boolean): void;
  filter(): void;
  highlight(index: number): void;
  choose(option: HTMLElement): void;
}

type Menu = Magics & State & Methods;

export function selectMenu() {
  return {
    open: false,
    query: "",
    active: -1,

    options(this: Menu): HTMLElement[] {
      return [...this.$refs.list.querySelectorAll<HTMLElement>("[role=option]")];
    },

    choices(this: Menu): HTMLElement[] {
      return this.options().filter((option) => !option.hidden && option.getAttribute("aria-disabled") !== "true");
    },

    toggle(this: Menu) {
      if (this.open) {
        this.close();
      } else {
        this.show();
      }
    },

    show(this: Menu) {
      this.open = true;
      this.query = "";
      this.filter();
      this.$nextTick(() => this.$refs.search.focus());
    },

    close(this: Menu, refocus = true) {
      this.open = false;
      this.active = -1;
      if (refocus) this.$refs.button.focus();
    },

    filter(this: Menu) {
      const term = this.query.trim().toLowerCase();
      let shown = 0;

      for (const option of this.options()) {
        option.hidden = term !== "" && !(option.dataset.search ?? "").includes(term);
        if (!option.hidden) shown++;
      }

      this.$refs.empty.hidden = shown > 0;
      this.highlight(this.choices().length ? 0 : -1);
    },

    highlight(this: Menu, index: number) {
      const choices = this.choices();
      this.active = index;

      for (const option of this.options()) option.removeAttribute("data-active");

      const current = choices[index];
      if (current) {
        current.setAttribute("data-active", "");
        current.scrollIntoView({ block: "nearest" });
        this.$refs.search.setAttribute("aria-activedescendant", current.id);
      } else {
        this.$refs.search.removeAttribute("aria-activedescendant");
      }
    },

    move(this: Menu, step: number) {
      const count = this.choices().length;
      if (count) this.highlight((this.active + step + count) % count);
    },

    chooseActive(this: Menu) {
      const current = this.choices()[this.active];
      if (current) this.choose(current);
    },

    choose(this: Menu, option: HTMLElement) {
      if (option.getAttribute("aria-disabled") === "true") return;

      this.close();
      this.$dispatch("select", { value: Number.isNaN(Number(option.dataset.value)) ? option.dataset.value : Number(option.dataset.value) });
    },
  };
}
