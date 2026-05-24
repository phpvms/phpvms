/**
 * Post-commit success card + auto-redirect.
 *
 * Rendered by PreviewPanel when commit succeeds. Shows a brief summary
 * (created count, batch_id, ids) then navigates to the new bundle's edit
 * page after a short delay. User can also click the manual link.
 *
 * The redirect URL is hardcoded against the standard Filament resource
 * route convention (`/admin/flight-bundles/{id}/edit`). If the prereq
 * change moves the bundle resource elsewhere, swap to a config-driven URL
 * template (window.routeforgeConfig.routes.bundle_edit_template or similar).
 *
 * Caller is responsible for clearDraft() — the redirect doesn't unmount
 * the React tree fast enough to rely on cleanup hooks.
 */

import { useEffect } from "preact/hooks";

import { t } from "../lib/i18n";
import type { CommitResponse } from "../state/types";

const REDIRECT_DELAY_MS = 1500;

export type CommitSuccessRedirectProps = {
  result: CommitResponse;
};

export function CommitSuccessRedirect({ result }: CommitSuccessRedirectProps) {
  const redirectUrl = `/admin/flight-bundles/${result.bundle_id}/edit`;

  useEffect(() => {
    const timer = setTimeout(() => {
      window.location.href = redirectUrl;
    }, REDIRECT_DELAY_MS);
    return () => clearTimeout(timer);
  }, [redirectUrl]);

  return (
    <div class="rounded-lg border border-green-300 bg-green-50 p-6 text-center dark:border-green-700 dark:bg-green-900/20">
      <div class="mb-2 text-3xl" aria-hidden="true">
        ✓
      </div>
      <h2 class="mb-1 text-base font-semibold text-green-900 dark:text-green-100">
        {t("commit.success", {
          count: result.created_count,
          batch_id: result.batch_id,
        })}
      </h2>
      <p class="text-sm text-gray-700 dark:text-gray-300">
        <a
          class="font-medium text-primary-600 underline hover:text-primary-700 dark:text-primary-400"
          href={redirectUrl}
        >
          {t("commit.go_now")}
        </a>
      </p>
    </div>
  );
}
