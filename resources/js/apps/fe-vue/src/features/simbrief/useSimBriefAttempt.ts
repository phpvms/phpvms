import { readonly, shallowRef } from "vue";
import { router } from "@inertiajs/vue3";

export type SimBriefAttemptState =
  | "ready"
  | "requesting-code"
  | "waiting-for-popup"
  | "polling"
  | "complete"
  | "error";

interface ApiCodeResponse {
  apiCode: string;
  providerUrl: string;
}

interface PollResponse {
  briefing?: App.Http.Data.SimBriefBriefingData;
  briefingUrl?: string;
  type?: string;
  message?: string;
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

function providerFormFields(
  planning: App.Http.Data.SimBriefPlanningData,
  apiCode: string,
): Record<string, string> {
  const outputPage = window.location.href.replace("http://", "");
  const timestamp = String(Math.round(Date.now() / 1000));
  const fields = Object.fromEntries(
    Object.entries(planning.providerFields).flatMap(([key, value]) =>
      value === null ? [] : [[key, String(value)]],
    ),
  );

  return { ...fields, apicode: apiCode, outputpage: outputPage, timestamp };
}

function submitProviderPopup(providerUrl: string, fields: Record<string, string>): Window | null {
  const popup = window.open("about:blank", "phpvms-simbrief", "popup,width=1240,height=900");
  if (!popup) return null;

  const form = document.createElement("form");
  form.method = "get";
  form.action = providerUrl;
  form.target = "phpvms-simbrief";
  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.append(input);
  }
  document.body.append(form);
  form.submit();
  form.remove();
  popup.focus();
  return popup;
}

export function useSimBriefAttempt(planning: App.Http.Data.SimBriefPlanningData) {
  const state = shallowRef<SimBriefAttemptState>("ready");
  const failure = shallowRef<string | null>(null);
  const briefingUrl = shallowRef<string | null>(null);
  const pollAttempts = shallowRef(0);
  let popup: Window | null = null;
  let popupWatch: number | null = null;
  let pollTimer: number | null = null;

  function clearTimers() {
    if (popupWatch !== null) window.clearInterval(popupWatch);
    if (pollTimer !== null) window.clearTimeout(pollTimer);
    popupWatch = null;
    pollTimer = null;
  }

  async function generate() {
    if (state.value !== "ready" && state.value !== "error") return;
    state.value = "requesting-code";
    failure.value = null;
    briefingUrl.value = null;
    pollAttempts.value = 0;

    try {
      const apiRequest = [
        planning.providerFields.orig,
        planning.providerFields.dest,
        planning.providerFields.type,
        Date.now(),
        window.location.href.replace("http://", ""),
      ].join("");
      const response = await fetch(
        `/simbrief/attempts/${encodeURIComponent(planning.attempt.staticId)}/api-code`,
        {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
          },
          body: JSON.stringify({ apiRequest }),
        },
      );
      if (!response.ok) throw new Error("SimBrief could not prepare this flight plan.");
      const result = (await response.json()) as ApiCodeResponse;
      popup = submitProviderPopup(result.providerUrl, providerFormFields(planning, result.apiCode));
      if (!popup) {
        state.value = "error";
        failure.value = "The SimBrief window was blocked. Allow popups, then try Generate again.";
        return;
      }
      state.value = "waiting-for-popup";
      popupWatch = window.setInterval(() => {
        if (!popup?.closed) return;
        if (popupWatch !== null) window.clearInterval(popupWatch);
        popupWatch = null;
        void poll();
      }, 500);
    } catch {
      state.value = "error";
      failure.value = "SimBrief could not be started. Check your connection and try again.";
    }
  }

  async function poll() {
    if (state.value === "complete") return;
    state.value = "polling";
    pollAttempts.value += 1;
    try {
      const response = await fetch(
        `/simbrief/attempts/${encodeURIComponent(planning.attempt.staticId)}/poll`,
        {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
          },
        },
      );
      const result = (await response.json()) as PollResponse;
      if (response.ok && result.briefingUrl) {
        state.value = "complete";
        briefingUrl.value = result.briefingUrl;
        clearTimers();
        router.visit(result.briefingUrl);
        return;
      }
      if (response.status === 409 && result.type === "ofp-not-ready" && pollAttempts.value < 8) {
        pollTimer = window.setTimeout(() => void poll(), 2_000);
        return;
      }
      state.value = "error";
      failure.value =
        result.message ??
        "The SimBrief flight plan was not ready. You can try again without losing your planning context.";
    } catch {
      state.value = "error";
      failure.value = "The briefing could not be checked. Check your connection and try again.";
    }
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
    pollAttempts: readonly(pollAttempts),
    retryPoll,
    state: readonly(state),
  };
}
