import { createInfiniteSelect } from "@phpvms/infinite-select";

export default function infiniteSelectFormComponent({
  state,
  placeholder,
  initialLabel,
  getPage,
  resolveLabel,
}) {
  return {
    state,
    select: null,
    syncing: false,

    init() {
      const hasState = this.state !== null && this.state !== undefined && this.state !== "";

      this.select = createInfiniteSelect(this.$refs.select, {
        getPage,
        resolveLabel,
        onError: () =>
          new window.FilamentNotification().title("Could not load options").danger().send(),
        placeholder,
        initial: hasState
          ? { value: String(this.state), label: initialLabel ?? String(this.state) }
          : null,
        onChange: (value) => {
          if (this.syncing) {
            return;
          }

          this.syncing = true;
          this.state = value;
          this.$nextTick(() => (this.syncing = false));
        },
      });

      this.$watch("state", (value) => {
        if (this.syncing) {
          return;
        }

        this.syncing = true;
        this.select.setValue(value);
        this.$nextTick(() => (this.syncing = false));
      });
    },

    destroy() {
      this.select?.destroy();
      this.select = null;
    },
  };
}
