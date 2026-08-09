# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Virtual airline (VA) owners and staff — flight-simulation hobbyists running a simulated
airline, not professional developers or designers. They use the Filament admin panel to run
day-to-day ops: reviewing filed flight reports (PIREPs), managing schedules, fleets/subfleets,
airports, users, finances, awards, and settings. Sessions are task-driven and often happen in
the evening; many admin tasks are data-dense (tables of flights, multi-tab edit forms).

## Product Purpose

phpVMS is the open-source platform for running a virtual airline: pilots register, view
schedules, fly them in a simulator, and file flight reports (via ACARS or manual). The admin
panel is the airline's operations office — success means staff complete reviews and edits
quickly, trust the numbers, and enjoy being in the tool.

## Positioning

The only mature, open-source, self-hosted VA platform with native ACARS integration,
deep financial modeling (per-flight/subfleet costs, fares), and a module ecosystem. It speaks
real aviation data natively: ICAO codes, routes, flight levels, METARs, SimBrief.

## Operating Context

Flight-sim culture artifacts staff know by heart: dispatch releases / operational flight plans,
EFB apps (ForeFlight-style), aeronautical charts, ATC scopes, departure boards, boarding
passes, FMS CDU pages. Admin work happens alongside a simulator, a browser of charts, and
Discord. Both light and dark environments occur; the panel must support both modes well.

## Capabilities and Constraints

- Admin panel is Filament 5 (Livewire + Alpine + Tailwind 4); theming happens through the
  panel provider (`app/Providers/Filament/AdminPanelProvider.php`) and one Vite theme CSS
  (`resources/css/filament/admin/theme.css`). Functionality, flows, labels, and Filament
  component behavior are out of scope for visual changes.
- Redesign confirmed: replace the incumbent visual world (Geist/Encode Sans, #067ec1 blue,
  white flat surfaces) with a fresh, modern one. Light-first, dark fully supported. Palette
  is free to move away from the legacy blue; the phpvms wordmark/logo stays.
- Modules can inject their own Filament resources/pages/widgets; the theme must style stock
  Filament primitives so module UIs inherit it automatically.
- Custom built surfaces already exist inside the admin (PIREP cards, PIREP detail v2, flight
  edit header, RouteForge) and must be reconciled with the new world.

## Brand Commitments

- Name/wordmark: "phpvms" + the existing logo mark (`public/assets/img/`) stay.
- Palette: explicitly free to change (confirmed 2026-08-05).
- Voice: utilitarian, aviation-fluent; no marketing copy inside the admin.

## Evidence on Hand

Real operational data throughout (flights, PIREPs, finances). Deployed reference instance:
https://phpvms.zsh.ad/admin. Do not fabricate testimonials or metrics anywhere.

## Product Principles

1. Task speed over decoration — expression never obscures state, data, or affordances.
2. Aviation-native information design — codes, times, and routes are first-class citizens.
3. One theme, every screen — stock Filament primitives carry the world so modules inherit it.
4. Light-first, dark-equal — both modes are designed, neither is an afterthought.
