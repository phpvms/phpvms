import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import type { HeaderUser } from "./headerTypes";

export type AppUser = HeaderUser;

interface ChromePageProps extends Record<string, unknown> {
  appName?: string;
  auth?: {
    user: AppUser | null;
  };
  pilotChrome?: App.Http.Data.PilotChromeData | null;
}

export function useAppChrome() {
  const page = usePage<ChromePageProps>();
  const user = computed(() => page.props?.auth?.user ?? null);
  const appName = computed(() => page.props?.appName ?? "phpVMS");
  const pilotChrome = computed(() => page.props?.pilotChrome ?? null);
  const initials = computed(() =>
    (user.value?.name ?? "?")
      .split(" ")
      .map((part) => part[0])
      .slice(0, 2)
      .join("")
      .toUpperCase(),
  );

  return { appName, initials, pilotChrome, user };
}
