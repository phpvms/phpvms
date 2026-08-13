/**
 * Boot failure UI for the RouteForge SPA.
 *
 * Rendered by main.tsx when the boot fetch fails (non-2xx, network error, or
 * missing data-boot-url attribute). No translations are available yet —
 * boot has to succeed before `t()` works — so strings are English literals.
 *
 * The Retry button re-runs the bootstrap flow; main.tsx replaces this
 * component with `<App />` on a successful retry.
 */

export type BootErrorProps = {
  message: string;
  onRetry: () => void;
};

export function BootError({ message, onRetry }: BootErrorProps) {
  return (
    <div class="rounded-lg border border-(--bad-line) bg-(--bad-soft) p-6">
      <h2 class="mb-2 text-base font-semibold text-(--bad)">RouteForge failed to load</h2>
      <p class="mb-4 text-sm text-(--bad)">{message}</p>
      <button type="button" class="fi-btn fi-color-danger" onClick={onRetry}>
        Retry
      </button>
    </div>
  );
}
