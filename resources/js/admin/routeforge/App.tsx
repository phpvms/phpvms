/**
 * Root component.
 *
 * Lifecycle:
 *
 *   1. Mount → call loadDraft() once. If a draft exists, hold it in local
 *      state so DraftResumeBanner can prompt; otherwise mark draftLoaded so
 *      the layout renders immediately.
 *
 *   2. After the user resolves the banner (Resume or Discard) — or when no
 *      draft existed in the first place — `draftLoaded.value` flips to true
 *      and the persistence effect arms.
 *
 *   3. Persistence effect: subscribes to every signal that belongs in the
 *      draft envelope (form, rows, the three caches) and pipes each change
 *      into saveDraft(). The 300ms debounce lives inside persistence.ts.
 *
 * The `skipFirst` flag inside the effect closure avoids the classic signal
 * footgun: `effect()` runs once synchronously when registered, which would
 * otherwise overwrite a freshly-resumed envelope with itself (harmless but
 * wasteful) and — more importantly — overwrite a discarded draft with the
 * just-reset empty state (which would resurrect the banner on next reload).
 *
 * Persistence is registered AFTER the resume flow settles, so resetStore()
 * mutations inside handleDiscard() happen before any subscriber exists.
 */

import { effect } from "@preact/signals";
import { useEffect, useState } from "preact/hooks";

import { DraftResumeBanner } from "./components/DraftResumeBanner";
import { FormPanel } from "./components/FormPanel";
import { PreviewPanel } from "./components/PreviewPanel";
import { setupLifecycle } from "./lib/lifecycle";
import { type LoadResult, loadDraft, saveDraft } from "./state/persistence";
import { airlineStats, airportCache, draftLoaded, form, rows, subfleetCache } from "./state/store";

export function App() {
  const [resumeResult, setResumeResult] = useState<LoadResult | null>(null);
  const [resumeChecked, setResumeChecked] = useState<boolean>(false);

  // One-shot: read localStorage on mount. If no draft, arm immediately;
  // otherwise let the banner drive arming via handleResume/handleDiscard.
  useEffect(() => {
    const result = loadDraft();
    if (result === null) {
      draftLoaded.value = true;
    } else {
      setResumeResult(result);
    }
    setResumeChecked(true);
  }, []);

  // Persistence effect + lifecycle effects (dirty tracker + auto-lint),
  // both gated on draftLoaded so banner-stage mutations don't accidentally
  // write OR trigger the dirty modal before the user has even started.
  const armed = draftLoaded.value;
  useEffect(() => {
    if (!armed) {
      return;
    }
    let skipFirst = true;
    const disposePersistence = effect(() => {
      const payload = {
        form: form.value,
        rows: rows.value,
        airports: airportCache.value,
        subfleets: subfleetCache.value,
        airline_stats: airlineStats.value,
      };
      if (skipFirst) {
        skipFirst = false;
        return;
      }
      saveDraft(payload);
    });
    const disposeLifecycle = setupLifecycle();
    return () => {
      disposePersistence();
      disposeLifecycle();
    };
  }, [armed]);

  if (!resumeChecked) {
    // First paint guard — avoids a 1-frame flash of the layout before
    // we know whether a draft exists.
    return null;
  }

  if (resumeResult !== null && !draftLoaded.value) {
    return <DraftResumeBanner result={resumeResult} onResolve={() => setResumeResult(null)} />;
  }

  return (
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
      <div>
        <FormPanel />
      </div>
      <div class="generated-flights sticky top-40 self-start">
        <PreviewPanel />
      </div>
    </div>
  );
}
