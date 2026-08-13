export interface NavigationDestination {
  label: string;
  href: string;
  spa: boolean;
  icon: string;
}

export const navigationDestinations: readonly NavigationDestination[] = [
  { label: "common.dashboard", href: "/dashboard", spa: true, icon: "layout-dashboard" },
  { label: "ui.nav_flights", href: "/flights", spa: true, icon: "plane" },
  { label: "ui.nav_bids", href: "/flights/bids", spa: true, icon: "checks" },
  { label: "common.live_map", href: "/livemap", spa: false, icon: "map" },
  { label: "ui.nav_logbook", href: "/pireps", spa: true, icon: "notebook" },
  { label: "common.profile", href: "/profile", spa: true, icon: "user" },
];
