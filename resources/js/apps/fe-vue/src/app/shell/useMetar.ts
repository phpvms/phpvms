import { onMounted, onUnmounted, readonly, shallowRef, toValue, watch } from "vue";
import type { MaybeRefOrGetter, WatchStopHandle } from "vue";
import type { MetarResponse, WeatherStation } from "./headerTypes";

export type MetarState =
  | { kind: "missing-station" }
  | { kind: "loading"; station: string }
  | { kind: "loaded"; weather: MetarResponse }
  | { kind: "missing"; station: string }
  | { kind: "stale"; weather: MetarResponse }
  | { kind: "error"; station: string };

export function useMetar(station: MaybeRefOrGetter<WeatherStation | null>) {
  const state = shallowRef<MetarState>({ kind: "missing-station" });
  let controller: AbortController | undefined;
  let stop: WatchStopHandle | undefined;

  async function load(nextStation = toValue(station)) {
    controller?.abort();

    if (!nextStation) {
      state.value = { kind: "missing-station" };
      return;
    }

    const request = new AbortController();
    controller = request;
    state.value = { kind: "loading", station: nextStation.icao };

    try {
      const response = await fetch(`/api/weather/${encodeURIComponent(nextStation.icao)}`, {
        signal: request.signal,
        headers: { Accept: "application/json" },
      });

      if (!response.ok) throw new Error("METAR request failed");
      const weather = (await response.json()) as MetarResponse;
      if (request.signal.aborted) return;

      if (!weather.metar) {
        state.value = { kind: "missing", station: nextStation.icao };
      } else if (weather.isStale) {
        state.value = { kind: "stale", weather };
      } else {
        state.value = { kind: "loaded", weather };
      }
    } catch {
      if (!request.signal.aborted) state.value = { kind: "error", station: nextStation.icao };
    }
  }

  onMounted(() => {
    stop = watch(
      () => toValue(station),
      (nextStation) => void load(nextStation),
      { immediate: true },
    );
  });

  onUnmounted(() => {
    stop?.();
    controller?.abort();
  });

  return { retry: () => load(), state: readonly(state) };
}
