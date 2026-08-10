import { onMounted, onUnmounted, readonly, shallowRef } from "vue";

/**
 * Live Zulu (UTC) clock, formatted `HH:MMZ`. Ticks every second on the client;
 * SSR-safe (renders an initial value, updates after mount).
 */
export function useZulu() {
  const format = (d: Date) =>
    `${String(d.getUTCHours()).padStart(2, "0")}:${String(d.getUTCMinutes()).padStart(2, "0")}Z`;

  const zulu = shallowRef(format(new Date()));
  let timer: ReturnType<typeof setInterval> | undefined;

  onMounted(() => {
    zulu.value = format(new Date());
    timer = setInterval(() => {
      zulu.value = format(new Date());
    }, 1000);
  });
  onUnmounted(() => {
    if (timer) clearInterval(timer);
  });

  return { zulu: readonly(zulu) };
}
