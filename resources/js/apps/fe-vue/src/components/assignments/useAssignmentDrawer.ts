import { readonly, shallowRef } from "vue";
import type { BidFailure, DispatchPayload } from "./types";

export type BidDrawerState = "idle" | "loading" | "selecting" | "submitting" | "overview" | "error";

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

export function useAssignmentDrawer() {
  const open = shallowRef(false);
  const flightId = shallowRef<string | null>(null);
  const state = shallowRef<BidDrawerState>("idle");
  const payload = shallowRef<DispatchPayload | null>(null);
  const selectedAircraftId = shallowRef<number | null>(null);
  const failure = shallowRef<BidFailure | null>(null);
  const selectionVersion = shallowRef(0);

  async function load(id = flightId.value) {
    if (!id) return;

    state.value = "loading";
    failure.value = null;

    try {
      const response = await fetch(`/flights/${encodeURIComponent(id)}/dispatch`, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) throw new Error("The flight could not be loaded.");

      const next = (await response.json()) as DispatchPayload;
      payload.value = next;
      selectedAircraftId.value = next.selection?.aircraft?.id ?? null;
      selectionVersion.value += 1;
      state.value = next.selection ? "overview" : "selecting";
    } catch {
      failure.value = {
        type: "network",
        message: "The flight could not be loaded. Check your connection and try again.",
      };
      state.value = "error";
    }
  }

  function show(id: string) {
    flightId.value = id;
    payload.value = null;
    selectedAircraftId.value = null;
    failure.value = null;
    open.value = true;
    void load(id);
  }

  function close() {
    if (state.value === "submitting") return;
    open.value = false;
  }

  async function submit() {
    if (!flightId.value || !payload.value || state.value === "submitting") return;
    if (payload.value.policy.aircraftRequired && selectedAircraftId.value === null) {
      failure.value = {
        type: "validation",
        message: "Select an aircraft before placing this bid.",
      };
      return;
    }

    state.value = "submitting";
    failure.value = null;

    try {
      const response = await fetch(`/flights/${encodeURIComponent(flightId.value)}/bid`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify({ aircraftId: selectedAircraftId.value }),
      });
      const body = (await response.json()) as {
        selection?: App.Http.Data.BidSelectionData;
        type?: string;
        message?: string;
      };

      if (!response.ok || !body.selection) {
        const nextFailure = {
          type: body.type ?? "network",
          message: body.message ?? "The bid could not be placed. Try again.",
        };
        failure.value = nextFailure;

        if (["aircraft-conflict", "stale-aircraft"].includes(nextFailure.type)) {
          const refresh = await fetch(`/flights/${encodeURIComponent(flightId.value)}/dispatch`, {
            headers: { Accept: "application/json" },
          });
          if (refresh.ok) {
            const next = (await refresh.json()) as DispatchPayload;
            payload.value = next;
            selectedAircraftId.value = null;
            selectionVersion.value += 1;
            failure.value = nextFailure;
          }
        }

        state.value = "selecting";
        return;
      }

      payload.value = {
        ...payload.value,
        selection: body.selection,
        flight: body.selection.flight,
      };
      state.value = "overview";
    } catch {
      failure.value = {
        type: "network",
        message: "The bid could not be placed. Check your connection and try again.",
      };
      state.value = "selecting";
    }
  }

  return {
    close,
    failure: readonly(failure),
    load,
    open: readonly(open),
    payload: readonly(payload),
    selectedAircraftId,
    selectionVersion: readonly(selectionVersion),
    show,
    state: readonly(state),
    submit,
  };
}
