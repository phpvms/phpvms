---
name: skylight-vue-pages
description: "Builds and edits the skylight pilot frontend (resources/js/apps/fe-vue) — Vue 3 + Inertia + Nuxt UI v4 SPA. Activates on ANY work touching a .vue file, page, widget, or component under resources/js/apps/fe-vue, on skylight/pilot-frontend/SPA page work, on Inertia page wiring or themed() controller responses for the SPA, and whenever the user mentions skylight, fe-vue, Nuxt UI, UPage/UPageHeader, pv-* CSS hooks, or the pilot dashboard/flights/tours/pireps/live map pages."
license: MIT
metadata:
  author: phpvms
---

# skylight pilot frontend (fe-vue)

Vue 3 + Inertia + Nuxt UI v4 SPA at `resources/js/apps/fe-vue`. This skill is the
short form of `resources/js/apps/fe-vue/AGENTS.md`; `resources/js/apps/fe-vue/THEMING.md`
is the binding styling contract. Read THEMING.md before inventing a CSS hook or token.

Package manager is **pnpm** — never `bun install` (the root `package.json` has no
`workspaces` key, so bun installs none of the SPA's deps).

## Page skeleton — always this shape

Pages are Nuxt UI components end to end. Never hand-roll page chrome.

```vue
<script setup lang="ts">
import UBadge from "@nuxt/ui/components/Badge.vue";
import UPage from "@nuxt/ui/components/Page.vue";
import UPageBody from "@nuxt/ui/components/PageBody.vue";
import UPageHeader from "@nuxt/ui/components/PageHeader.vue";

const props = defineProps<{ tours: App.Http.Data.TourListItemData[] }>();
</script>

<template>
  <UPage class="pv-tours" aria-label="Tours">
    <UPageHeader
      headline="Flights"
      title="Tours"
      description="One-sentence summary of the page."
    >
      <template #links>
        <UBadge color="neutral" variant="subtle" size="lg">2 tours</UBadge>
      </template>
    </UPageHeader>

    <UPageBody>
      <!-- content -->
    </UPageBody>

    <!-- shared overlays (e.g. AssignmentDrawer) last -->
  </UPage>
</template>
```

1. **Root is `UPage`** with a `.pv-<page>` class and an `aria-label`. New `.pv-*`
   hooks must be added to THEMING.md's supported-hooks table.
2. **Header is `UPageHeader` props** — `headline` (the section, e.g. "Flights"),
   `title`, `description`, and the `#links` slot for right-side controls.
   Do NOT put `pv-eyebrow`/`<h1>` markup in the default slot.
3. **Content sits in `UPageBody`.**

Reference implementations: `pages/Tours/Index.vue`, `pages/LiveMap/Index.vue`,
`pages/Flights.vue`, `pages/Flights/Bids.vue`, the Ofp pages. **Not** the
pattern (predate the contract, do not copy them): `pages/Pireps/Index.vue`,
`pages/Flights/Show.vue`.

## Nuxt UI imports are explicit

Auto-import is off (`components: false, autoImport: false`). Import each
component by file — `import UPage from "@nuxt/ui/components/Page.vue"`. A missing
import is silent at build time; it only shows as `[Vue warn]: Failed to resolve
component` in the browser console.

**`Icon` and `Link` are the exceptions** — the `@nuxt/ui/components/` copies are
the Nuxt build's and break here. Use
`@nuxt/ui/runtime/vue/components/Icon.vue` and
`@nuxt/ui/runtime/vue/overrides/inertia/Link.vue`. Nuxt UI's own internals get
these automatically: `internalResolverPlugin` still rewrites its relative
`./Icon.vue` / `./Link.vue` imports even with `components: false`.

Icons are **not** `UIcon` — they come from unplugin-icons:
`import IconRoute from "~icons/tabler/route"`, then passed as a prop
(`:icon="IconRoute"`).

Component unit tests do not need to stub `U*`: `vitest.config.ts` installs a
`nuxt-ui-component-stubs` plugin that resolves every Nuxt UI component import to
a passthrough rendering its own tag. Pass your own `global.stubs` entry (matched
on the `U<Name>` name) only when a test needs real behaviour.

## Links: `to` is already an Inertia visit

`vite.config.ts` passes `router: "inertia"` to the Nuxt UI plugin. That rewrites
Nuxt UI's internal relative `./Link.vue` / `./LinkBase.vue` imports to
`@nuxt/ui/dist/runtime/vue/overrides/inertia/`, whose `LinkBase` renders `<Link>`
from `@inertiajs/vue3` for any internal href. So `<UButton to="/tours/1">` is a
real Inertia visit, not a full page reload.

**Do not reason about this from `runtime/components/Link.vue` / `LinkBase.vue`.**
Those are the Nuxt build's versions — `LinkBase` there forces `as: "a"` on any
href — and they never load here. `vue-router` is not even installed, and the
vue-router override imports it, so only the inertia override can be in the graph.

Inertia's own `<Link>` is still the right choice when the attrs have to land on
the element itself: `UPageCard` forwards `$attrs` to its root only when `to` is
unset, so a card that also needs `role="listitem"` uses
`<UPageCard :as="Link" :href="...">` (`components/pireps/PirepCard.vue`).

## Components before custom markup

1. Grep `node_modules/@nuxt/ui/dist/runtime/components/` first — it ships more
   than the obvious controls: `UBlogPosts`/`UBlogPost` (image card grids),
   `UTimeline` (progress chains), `UEmpty`, `UStepper`, `UBadge`, `UTabs`, ...
   Use their **props/slots API**, not custom markup stuffed into a bare slot.
2. Before writing a card or header, grep `src/components/` — `FlightIdentHeader`,
   `FlightStats`, `TourLegTimeline` and `PvFlightInfo` already exist. Extend one
   with a prop rather than duplicating it.
3. Overriding `UBlogPost`'s `#header` slot replaces its image rendering — render
   the `<img>` yourself with the slot's `ui.image()` classes.

## Styling order (THEMING.md customization hierarchy)

1. Theme tokens, then Nuxt UI component defaults.
2. Spacing/alignment fixes go through the component's **`:ui` slot-class
   override**, not parallel scoped CSS. Theme defaults live in
   `@nuxt/ui/dist/shared/ui.*.mjs`. Example:
   `:ui="{ wrapper: 'mt-0 pb-4', title: 'leading-6' }"`.
3. `--pv-*` variables for phpVMS domain components.
4. **Scoped CSS is a last resort.** When you must: wrap it in
   `@layer components`, use `--pv-*` semantic tokens only (never raw colors),
   and keep it to data-font (mono) and small layout glue. A bare
   `<style scoped>` with no `@layer components` is a violation.
5. Never add an unscoped `<style>` block to a component.

## Where files live

1. **Pages** — one directory per feature once it has more than one page:
   `pages/Tours/Index.vue` + `pages/Tours/Show.vue`. The Inertia component name
   is the path (`'Tours/Index'`), so moving a page means updating the
   controller's `themed()` first argument and the `->component(...)` assertion
   in its test.
2. **Domain components** — `components/<domain>/` (`flights/`, `tours/`,
   `assignments/`, `pilots/`, `live-map/`, `simbrief/`). Anything two pages
   could show goes here, not inline in a page.
3. **Pages own state and side effects; components emit.** A route view is a thin
   composition surface: it holds the drawer ref, does the fetch, reloads the
   prop; the card emits `open`/`cancel`. That keeps a card ejectable (THEMING.md)
   and testable.
4. **Shared types** go in the domain's `types.ts` — a `<script setup>` block
   cannot `export`.

## Vue rules

- `<script setup lang="ts">` on every SFC. No Options API, no untyped SFCs.
- SFC section order: `<script>` → `<template>` → `<style>`.
- Typed contracts: `defineProps<...>()` / `defineEmits<...>()`. Props down,
  events up. `provide/inject` only for genuine deep-tree context.
- Keep source state minimal (`ref`); derive with `computed`. Watchers for side
  effects only.
- Split a component that owns both orchestration and several presentational
  sections. Extract reusable stateful logic to `shared/lib/use*.ts`.

## Wiring a new page

1. Drop `src/pages/<Name>.vue` — the Inertia glob in `src/app/main.ts`
   registers it; no manual registration.
2. Controller returns `response()->themed('<Name>', '<blade.view>',
   bladeData: ..., spa: ...)` with `#[TypeScript]` DTOs from `app/Http/Data`.
   Run `php artisan typescript:transform` after DTO changes; types are ambient
   as `App.Http.Data.*` (no import).
3. Nav entry in `src/app/shell/navigation.ts` + a `ui.php` lang key (the `ui`
   group is shared to the SPA). Page copy itself is plain English.
4. Provide a Blade fallback view for the `seven` theme (blade-kind installs hit
   the same route).
5. Backend tests in `tests/Feature/Skylight/`: `Theme::set('skylight')` +
   `updateSetting('general.theme', 'skylight')` in `beforeEach`, then
   `assertInertia` on component + props. Component unit tests live in the
   sibling tests package at `resources/js/apps/fe-vue/tests/src/**/*.test.ts`
   (vitest + @vue/test-utils, jsdom, `@` aliases `src/`).

## Checks before finishing

From the repo root unless noted:

```
pnpm exec oxfmt <file>
pnpm exec oxlint <file>
pnpm --filter @phpvms/skylight run typecheck
pnpm run build:fe
```

Plus Pest tests for the controller/DTO side.

Do not self-certify a visual result by measurement — do one visual-fix round,
then show the user.
