import {
  pollProviderAttempt,
  reserveProviderPopup,
  requestProviderSubmission,
  submitProviderGet,
} from "@phpvms/simbrief";
import { departureTiming } from "./ofp/departure-timing.js";

/**
 * The SimBrief generate flow on the OFP planning page, as skylight's
 * useSimBriefAttempt does it: the popup is reserved synchronously on click (so
 * the browser does not block it), the api code is requested, the provider form
 * is submitted into the popup, then the attempt is polled every 3s up to 60
 * times. When the briefing is ready the popup closes and the page goes to it.
 *
 * Mounts on `[data-airside-island="simbrief-attempt"]`, reads its data from the
 * child `<script type="application/json">` and the options from the
 * `[data-role="options"]` form.
 */
const NAME = "simbrief-attempt";
const POPUP_NAME = "phpvms-simbrief";
const POLL_INTERVAL_MS = 3_000;
const POLL_MAX_ATTEMPTS = 60;
const cleanups = new Set();

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? "";
}

function mountIsland(root) {
  const data = JSON.parse(
    root.querySelector('script[type="application/json"]')?.textContent ?? "null",
  );
  if (!data) return () => {};

  const generateButton = root.querySelector('[data-role="generate"]');
  const retryButton = root.querySelector('[data-role="retry"]');
  const stateBadge = root.querySelector('[data-role="state-badge"]');
  const failureText = root.querySelector('[data-role="failure"]');
  const pollingNode = root.querySelector('[data-role="polling"]');
  const form = root.querySelector('[data-role="options"]');

  let state = "ready";
  let popup = null;
  let popupWatch = null;
  let disposed = false;

  const transport = () => ({
    apiCodeUrl: data.apiCodeUrl,
    pollUrl: data.pollUrl,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken(),
    },
  });

  function render(failure = null, attempts = 0) {
    root.dataset.state = state;

    if (stateBadge) stateBadge.textContent = state.replaceAll("-", " ");
    if (generateButton) {
      generateButton.disabled = !["ready", "error"].includes(state);
      generateButton.setAttribute("aria-busy", state === "requesting-code" ? "true" : "false");
    }
    if (retryButton) retryButton.hidden = state !== "error";
    if (failureText) failureText.textContent = failure ?? "";

    for (const node of root.querySelectorAll("[data-show]")) {
      node.hidden = node.dataset.show !== state;
    }

    if (pollingNode) {
      const text = `Checking for the completed OFP (${attempts}/${POLL_MAX_ATTEMPTS})`;
      pollingNode.setAttribute("aria-label", text);
      const label = pollingNode.querySelector("span:last-child");
      if (label) label.textContent = text;
    }
  }

  function setState(next, failure = null, attempts = 0) {
    state = next;
    render(failure, attempts);
  }

  function showTiming() {
    const timing = departureTiming(data.blockTime);
    const etd = root.querySelector('[data-role="etd"]');
    const date = root.querySelector('[data-role="date"]');
    if (etd) etd.value = timing.etd;
    if (date) date.value = timing.date;

    return timing;
  }

  function overrides() {
    const timing = showTiming();
    const values = new FormData(form);
    const stepclimbs = String(values.get("stepclimbs") ?? "0");
    const result = {};

    for (const [key, value] of values.entries()) {
      result[key] = String(value);
    }

    // A disabled flight level is left out of FormData; the provider then falls back to its own.
    result.fl = stepclimbs === "1" ? null : String(values.get("fl") ?? "");

    return {
      ...result,
      date: timing.date,
      deph: timing.deph,
      depm: timing.depm,
      steh: timing.steh,
      stem: timing.stem,
    };
  }

  function watchPopup() {
    popupWatch = window.setInterval(() => {
      if (!popup?.closed) return;
      window.clearInterval(popupWatch);
      popupWatch = null;
      popup = null;
    }, 500);
  }

  async function poll() {
    if (state === "complete" || state === "polling") return;

    setState("polling", null, 0);
    try {
      const result = await pollProviderAttempt(transport(), {
        intervalMs: POLL_INTERVAL_MS,
        maxAttempts: POLL_MAX_ATTEMPTS,
        onAttempt: (attempt) => {
          if (!disposed) render(null, attempt);
        },
      });

      if (disposed) return;

      if (result.briefingUrl) {
        setState("complete");
        popup?.close();
        popup = null;
        window.location.assign(result.briefingUrl);
      }
    } catch (error) {
      if (disposed) return;

      setState(
        "error",
        error instanceof Error
          ? error.message
          : "The briefing could not be checked. Check your connection and try again.",
      );
    }
  }

  async function generate() {
    if (state !== "ready" && state !== "error") return;

    // Synchronous with the click, or the browser blocks the window.
    popup = reserveProviderPopup(POPUP_NAME);
    if (!popup) {
      setState(
        "error",
        "The SimBrief window was blocked. Allow popups, then try Generate OFP again.",
      );

      return;
    }

    setState("requesting-code");

    let submission;
    try {
      submission = await requestProviderSubmission(
        transport(),
        data.providerFields,
        overrides(),
        window.location.href,
      );
    } catch {
      setState("error", "SimBrief could not be started. Check your connection and try again.");
      popup.close();
      popup = null;

      return;
    }

    setState("waiting-for-popup");
    submitProviderGet(submission, popup);
    popup.focus();
    watchPopup();
    void poll();
  }

  const onGenerate = () => void generate();
  const onRetry = () => {
    if (state !== "polling") void poll();
  };

  generateButton?.addEventListener("click", onGenerate);
  retryButton?.addEventListener("click", onRetry);
  showTiming();
  render();

  return () => {
    disposed = true;
    if (popupWatch !== null) window.clearInterval(popupWatch);
    generateButton?.removeEventListener("click", onGenerate);
    retryButton?.removeEventListener("click", onRetry);
  };
}

function mountAll() {
  for (const root of document.querySelectorAll(`[data-airside-island="${NAME}"]`)) {
    if (root.dataset.mounted === "true") continue;
    root.dataset.mounted = "true";
    cleanups.add(mountIsland(root));
  }
}

function unmountAll() {
  for (const cleanup of cleanups) cleanup();
  cleanups.clear();
}

document.addEventListener("DOMContentLoaded", mountAll);
document.addEventListener("livewire:navigated", mountAll);
document.addEventListener("livewire:navigating", unmountAll);

if (document.readyState !== "loading") mountAll();
