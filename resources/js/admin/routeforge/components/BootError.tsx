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
    <div class="rounded-lg border border-red-300 bg-red-50 p-6 dark:border-red-700 dark:bg-red-900/20">
      <h2 class="mb-2 text-base font-semibold text-red-900 dark:text-red-100">
        RouteForge failed to load
      </h2>
      <p class="mb-4 text-sm text-red-800 dark:text-red-200">{message}</p>
      <button
        type="button"
        class="inline-flex items-center rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-red-900"
        onClick={onRetry}
      >
        Retry
      </button>
    </div>
  );
}
