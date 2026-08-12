import { readonly, shallowRef } from "vue";
import type { BidFailure, DispatchPayload, EligibleAircraftResponse } from "./types";

export type BidDrawerState = "idle" | "loading" | "selecting" | "submitting" | "overview" | "error";

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

export function useFlightBidDrawer() {
  const open = shallowRef(false);
  const flightId = shallowRef<string | null>(null);
  const state = shallowRef<BidDrawerState>("idle");
  const payload = shallowRef<DispatchPayload | null>(null);
  const aircraft = shallowRef<App.Http.Data.EligibleAircraftData[]>([]);
  const selectedSubfleetId = shallowRef<number | null>(null);
  const selectedAircraftId = shallowRef<number | null>(null);
  const loadingAircraft = shallowRef(false);
  const failure = shallowRef<BidFailure | null>(null);

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
      aircraft.value = [];
      selectedSubfleetId.value = next.selection?.aircraft?.subfleetId ?? null;
      selectedAircraftId.value = next.selection?.aircraft?.id ?? null;
      state.value = next.selection ? "overview" : "selecting";

      if (!next.selection && selectedSubfleetId.value !== null) {
        void loadAircraft(selectedSubfleetId.value);
      }
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
    aircraft.value = [];
    selectedSubfleetId.value = null;
    selectedAircraftId.value = null;
    failure.value = null;
    open.value = true;
    void load(id);
  }

  async function loadAircraft(subfleetId = selectedSubfleetId.value) {
    if (!flightId.value || subfleetId === null) return;

    loadingAircraft.value = true;
    failure.value = null;
    selectedAircraftId.value = null;

    try {
      const response = await fetch(
        `/flights/${encodeURIComponent(flightId.value)}/dispatch/subfleets/${encodeURIComponent(subfleetId)}/aircraft`,
        { headers: { Accept: "application/json" } },
      );
      if (!response.ok) throw new Error("Aircraft could not be loaded.");

      const next = (await response.json()) as EligibleAircraftResponse;
      if (selectedSubfleetId.value !== subfleetId) return;
      aircraft.value = next.aircraft;
    } catch {
      if (selectedSubfleetId.value !== subfleetId) return;
      aircraft.value = [];
      failure.value = {
        type: "network",
        message: "Eligible aircraft could not be loaded. Check your connection and try again.",
      };
    } finally {
      if (selectedSubfleetId.value === subfleetId) {
        loadingAircraft.value = false;
      }
    }
  }

  function selectSubfleet(subfleetId: number | null) {
    selectedSubfleetId.value = subfleetId;
    selectedAircraftId.value = null;
    aircraft.value = [];
    if (subfleetId !== null) void loadAircraft(subfleetId);
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
        const previousSubfleetId = selectedSubfleetId.value;
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
            aircraft.value = [];
            selectedAircraftId.value = null;
            if (previousSubfleetId !== null) {
              selectedSubfleetId.value = previousSubfleetId;
              void loadAircraft(previousSubfleetId);
            }
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
    aircraft: readonly(aircraft),
    loadingAircraft: readonly(loadingAircraft),
    load,
    loadAircraft,
    open: readonly(open),
    payload: readonly(payload),
    selectedAircraftId,
    selectedSubfleetId: readonly(selectedSubfleetId),
    selectSubfleet,
    show,
    state: readonly(state),
    submit,
  };
}
