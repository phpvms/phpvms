/**
 * Wiring between a Livewire component and its drawer, for drawers opened by a
 * browser event. `<x-vms::ui.drawer>` sets it up from its `event`, `wire:model`
 * and `subject`:
 *
 *   <x-vms::ui.drawer event="assignment-drawer" subject="flightId" wire:model="open">
 *
 * - `<event>.open` (the window event the opener dispatches) shows the drawer
 *   at once, with `opening` true until the component sends `<event>.loaded`.
 *   The component's own `#[On('<event>.open')]` loads the data.
 * - Closing from the drawer's close button, backdrop or Escape only flips the
 *   model in the browser, so that calls `$wire.close()`. With `subject` (a
 *   component property that is null once closed), a close that came from the
 *   server is not sent back.
 */
interface Wire {
  close(): void;
  $watch(property: string, callback: (value: unknown) => void): void;
  [property: string]: unknown;
}

export function livewireDrawer(event: string, model = "open", subject: string | null = null) {
  let onOpen: (() => void) | null = null;
  let onLoaded: (() => void) | null = null;

  return {
    opening: false,

    init(this: { $wire: Wire; opening: boolean }) {
      onOpen = () => {
        this.opening = true;
        this.$wire[model] = true;
      };
      onLoaded = () => {
        this.opening = false;
      };

      window.addEventListener(`${event}.open`, onOpen);
      window.addEventListener(`${event}.loaded`, onLoaded);

      this.$wire.$watch(model, (value) => {
        if (!value && (subject === null || this.$wire[subject] !== null)) {
          this.$wire.close();
        }
      });
    },

    destroy() {
      if (onOpen) window.removeEventListener(`${event}.open`, onOpen);
      if (onLoaded) window.removeEventListener(`${event}.loaded`, onLoaded);
    },
  };
}
