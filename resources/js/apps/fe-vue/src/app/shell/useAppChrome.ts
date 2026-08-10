import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

export interface AppUser {
  name: string;
  avatar: string | null;
}

interface SharedAuth {
  user: AppUser | null;
}

export function useAppChrome() {
  const page = usePage();
  const user = computed(() => (page.props.auth as SharedAuth | undefined)?.user ?? null);
  const appName = computed(() => (page.props.appName as string) ?? "phpVMS");
  const station = computed(() => {
    const airport = page.props.currentAirport as string | { icao?: string } | null | undefined;

    return !airport ? null : typeof airport === "string" ? airport : (airport.icao ?? null);
  });
  const initials = computed(() =>
    (user.value?.name ?? "?")
      .split(" ")
      .map((part) => part[0])
      .slice(0, 2)
      .join("")
      .toUpperCase(),
  );

  return { appName, initials, station, user };
}
