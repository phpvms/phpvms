import { readonly, shallowRef } from "vue";
import { router } from "@inertiajs/vue3";
import {
  pollProviderAttempt,
  reserveProviderPopup,
  requestProviderSubmission,
  submitProviderGet,
  type ProviderSubmission,
} from "@phpvms/simbrief";
import type { SimBriefEditableOverrides } from "./SimBriefPlanningOptions.vue";

export type SimBriefAttemptState =
  | "ready"
  | "requesting-code"
  | "waiting-for-popup"
  | "polling"
  | "complete"
  | "error";

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

export function useSimBriefAttempt(planning: App.Http.Data.SimBriefPlanningData | null) {
  const state = shallowRef<SimBriefAttemptState>("ready");
  const failure = shallowRef<string | null>(null);
  const briefingUrl = shallowRef<string | null>(null);
  const pollAttempts = shallowRef(0);
  let popup: Window | null = null;
  let popupWatch: number | null = null;

  function transport() {
    if (!planning) throw new Error("SimBrief planning is unavailable.");

    const headers = {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken(),
    };
    return {
      apiCodeUrl: `/ofp/attempts/${encodeURIComponent(planning.attempt.staticId)}/api-code`,
      headers,
      pollUrl: `/ofp/attempts/${encodeURIComponent(planning.attempt.staticId)}/poll`,
    };
  }

  function clearTimers() {
    if (popupWatch !== null) window.clearInterval(popupWatch);
    popupWatch = null;
  }

  async function prepare(
    overrides: SimBriefEditableOverrides = {},
  ): Promise<ProviderSubmission | null> {
    if (state.value !== "ready" && state.value !== "error") return null;
    if (!planning) return null;
    state.value = "requesting-code";
    failure.value = null;
    briefingUrl.value = null;
    pollAttempts.value = 0;

    try {
      const submission = await requestProviderSubmission(
        transport(),
        planning.providerFields,
        overrides,
        window.location.href,
      );
      state.value = "waiting-for-popup";
      return submission;
    } catch {
      state.value = "error";
      failure.value = "SimBrief could not be started. Check your connection and try again.";
      return null;
    }
  }

  function watchPopup() {
    popupWatch = window.setInterval(() => {
      if (!popup?.closed) return;
      if (popupWatch !== null) window.clearInterval(popupWatch);
      popupWatch = null;
      popup = null;
    }, 500);
  }

  async function generate(overrides: SimBriefEditableOverrides = {}) {
    popup = reserveProviderPopup("phpvms-simbrief");
    if (!popup) {
      state.value = "error";
      failure.value = "The SimBrief window was blocked. Allow popups, then try Generate OFP again.";
      return;
    }

    const submission = await prepare(overrides);
    if (!submission) {
      popup.close();
      popup = null;
      return;
    }

    submitProviderGet(submission, popup);
    popup.focus();
    watchPopup();
    startPolling();
  }

  async function generateEmbedded(overrides: SimBriefEditableOverrides = {}) {
    return prepare(overrides);
  }

  function openInPopup(submission: ProviderSubmission) {
    popup = reserveProviderPopup("phpvms-simbrief");
    if (!popup) {
      state.value = "error";
      failure.value = "The SimBrief window was blocked. Allow popups, then try again.";
      return false;
    }

    submitProviderGet(submission, popup);
    popup.focus();
    watchPopup();
    startPolling();
    return true;
  }

  async function poll() {
    if (state.value === "complete" || state.value === "polling") return;
    if (!planning) return;
    state.value = "polling";
    try {
      const result = await pollProviderAttempt(transport(), {
        intervalMs: 3_000,
        maxAttempts: 60,
        onAttempt: (value) => {
          pollAttempts.value = value;
        },
      });
      if (result.briefingUrl) {
        state.value = "complete";
        briefingUrl.value = result.briefingUrl;
        clearTimers();
        popup?.close();
        popup = null;
        router.visit(result.briefingUrl);
      }
    } catch (error) {
      state.value = "error";
      failure.value =
        error instanceof Error
          ? error.message
          : "The briefing could not be checked. Check your connection and try again.";
    }
  }

  function startPolling() {
    void poll();
  }

  function retryPoll() {
    if (state.value === "polling") return;
    void poll();
  }

  function dispose() {
    clearTimers();
  }

  return {
    briefingUrl: readonly(briefingUrl),
    dispose,
    failure: readonly(failure),
    generate,
    generateEmbedded,
    openInPopup,
    pollAttempts: readonly(pollAttempts),
    retryPoll,
    startPolling,
    state: readonly(state),
  };
}
