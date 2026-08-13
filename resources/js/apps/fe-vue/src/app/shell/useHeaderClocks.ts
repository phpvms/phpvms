import { computed, onMounted, onUnmounted, readonly, shallowRef, toValue } from "vue";
import type { MaybeRefOrGetter } from "vue";
import type { WeatherStation } from "./headerTypes";

function formatUtc(date: Date) {
  return `${String(date.getUTCHours()).padStart(2, "0")}:${String(date.getUTCMinutes()).padStart(2, "0")}Z`;
}

function formatLocal(date: Date, timezone: string | null | undefined) {
  if (!timezone) return null;

  try {
    return new Intl.DateTimeFormat(undefined, {
      hour: "2-digit",
      hourCycle: "h23",
      minute: "2-digit",
      timeZone: timezone,
    }).format(date);
  } catch {
    return null;
  }
}

export function useHeaderClocks(station: MaybeRefOrGetter<WeatherStation | null>) {
  const now = shallowRef(new Date());
  let timer: ReturnType<typeof setTimeout> | undefined;

  function tickAtMinuteBoundary() {
    now.value = new Date();
    const delay = 60_000 - (Date.now() % 60_000) + 25;
    timer = setTimeout(tickAtMinuteBoundary, delay);
  }

  onMounted(tickAtMinuteBoundary);
  onUnmounted(() => {
    if (timer) clearTimeout(timer);
  });

  const timezone = computed(() => toValue(station)?.timezone ?? null);

  return {
    local: computed(() => formatLocal(now.value, timezone.value)),
    timezone: readonly(timezone),
    utc: computed(() => formatUtc(now.value)),
  };
}
