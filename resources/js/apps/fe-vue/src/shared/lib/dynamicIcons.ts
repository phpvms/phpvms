import type { Component } from "vue";
import IconActivity from "~icons/tabler/activity";
import IconChecks from "~icons/tabler/checks";
import IconClock from "~icons/tabler/clock";
import IconLayoutDashboard from "~icons/tabler/layout-dashboard";
import IconMap from "~icons/tabler/map";
import IconNotebook from "~icons/tabler/notebook";
import IconPackages from "~icons/tabler/packages";
import IconPlane from "~icons/tabler/plane";
import IconPlaneArrival from "~icons/tabler/plane-arrival";
import IconPoint from "~icons/tabler/point";
import IconRadar from "~icons/tabler/radar";
import IconRoute from "~icons/tabler/route";
import IconUser from "~icons/tabler/user";
import IconUserPlus from "~icons/tabler/user-plus";
import IconWallet from "~icons/tabler/wallet";

/**
 * The icons a server-owned name can resolve to at runtime.
 *
 * Navigation destinations, widget definitions, and activity events carry their
 * icon as a string from PHP, so those cannot be static imports at their call
 * sites. Every name the backend is allowed to send is imported here instead, so
 * the set stays explicit and compiled into the bundle. Keys mirror
 * `dynamicTablerIcons` in `icon.config.ts` — `dynamic-icons.test.ts` fails if
 * the two drift apart.
 */
export const dynamicIcons: Readonly<Record<string, Component>> = {
  "i-tabler-activity": IconActivity,
  "i-tabler-checks": IconChecks,
  "i-tabler-clock": IconClock,
  "i-tabler-layout-dashboard": IconLayoutDashboard,
  "i-tabler-map": IconMap,
  "i-tabler-notebook": IconNotebook,
  "i-tabler-packages": IconPackages,
  "i-tabler-plane": IconPlane,
  "i-tabler-plane-arrival": IconPlaneArrival,
  "i-tabler-radar": IconRadar,
  "i-tabler-route": IconRoute,
  "i-tabler-user": IconUser,
  "i-tabler-user-plus": IconUserPlus,
  "i-tabler-wallet": IconWallet,
};

/**
 * Drawn when a name resolves to nothing — an addon asking for an icon phpVMS
 * does not bundle. A neutral dot, so an unknown name reads as a marker rather
 * than as the wrong icon.
 */
export const fallbackIcon: Component = IconPoint;

/** Convert `cloudSun` / `CloudSun` to Iconify's `cloud-sun` name. */
function toKebabCase(name: string): string {
  return name
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
    .replace(/[_\s]+/g, "-")
    .toLowerCase();
}

/**
 * Normalize a server-supplied icon name to a `dynamicIcons` key. Accepts a
 * full `i-tabler-plane` name, a bare `plane`, or camel/Pascal `PlaneArrival`.
 */
export function normalizeIconName(name: string): string {
  const trimmed = name.trim();
  if (trimmed.startsWith("i-tabler-")) {
    return trimmed;
  }
  return `i-tabler-${toKebabCase(trimmed.replace(/^i-[^-]+-/, ""))}`;
}

/** The component for a server-supplied icon name, or the fallback dot. */
export function resolveIcon(name: string): Component {
  return dynamicIcons[normalizeIconName(name)] ?? fallbackIcon;
}
