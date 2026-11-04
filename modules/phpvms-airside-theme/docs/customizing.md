# Customizing airside

airside is styled so a site can change how it looks without touching code or
copying the theme. There are three layers, each one overriding the one before:

1. **Shipped look.** `resources/css/airside.css` (imported by the build entry `resources/css/styles.css`), built with Tailwind. Changing it
   needs a rebuild, so only theme authors edit it.
2. **Theme options.** Settings an admin picks, such as full-width pages. Each
   option sets an attribute on `<html>` that `airside.css` already styles.
   (Planned; see the `theme-customization` openspec change.)
3. **Custom CSS.** Plain CSS an admin writes on the Content > Theme page. It is
   saved to the database and applies on the next page load, with no rebuild.

Every element worth styling carries a **hook**: a stable class name, an ARIA or
data attribute for its state, and CSS variables for values that come from data.
Both `airside.css` and Custom CSS target the same hooks. The full list is in
[theme-hooks.md](theme-hooks.md). The examples below include hooks that do not
exist yet (`vms-ofp-tab`, `vms-live-map-widget`, `vms-page`); theme-hooks.md lists
what exists.

## Hooks in markup

Hooks start with `vms-`, a prefix that does not name the theme, so a copied or child theme keeps the same hooks. They go on blocks a site
would plausibly restyle, not on every element. There are two kinds:

- **Scope hooks** on each page and each component root, named after it:
  `vms-flights`, `vms-flight-summary`, `vms-auth-card`.
- **Pattern hooks** on repeated parts, named the same everywhere: `vms-table`,
  `vms-table-row`, `vms-card`. One rule styles every instance.

Combine them to target one place: `.vms-flights .vms-flight-card`. Add a
block-specific name (`vms-flight-leg-pill`) only when one scope holds two of
the same pattern that must look different, or for a part with no pattern. The
markup keeps layout utilities only (grid, flex, gap, padding); colour, border,
background, radius and shadow belong in CSS.

```blade
<section {{ $attributes->class('vms-auth-card w-full') }}>
    <div class="grid gap-4 p-6">
        <h1 class="vms-auth-card-title">{{ $title }}</h1>
        {{ $slot }}
    </div>
    <footer class="vms-auth-card-footer flex justify-between gap-2 px-6 py-3">{{ $footer }}</footer>
</section>
```

State comes from the attribute the element already has for accessibility, not
from an extra class:

| Element | State attribute |
| --- | --- |
| Tab | `aria-selected="true"` |
| Current nav link | `aria-current="page"` |
| Toggle or picker button | `aria-pressed="true"` |
| Anything else | `data-state="active"` |

Hover and disabled come from `:hover` and `:disabled` in CSS.

A value that comes from data, such as a widget's configured width, is passed as a
CSS variable. The hook rule decides what to do with it:

```blade
<div class="vms-live-map-widget" style="--widget-width: {{ $config['width'] }}">
```

## Components

Views use TallStackUI components directly (`<x-ts-alert>`, `<x-ts-card>` and so
on). airside adds its own component only when TallStackUI has none (page, page
header, empty state, drawer with focus trap, icon mapping, stats list, file list)
or to eject one.

airside's components are anonymous Blade components under
`resources/views/components`. They are registered with
`Blade::anonymousComponentNamespace('components', 'vms')`, so the tag is
`<x-vms::…>`. Folders map to dots: `components/page/header.blade.php` is
`<x-vms::page.header>`, and `components/page/index.blade.php` is
`<x-vms::page>`. Folders are by function, then by feature: `layouts/`, `shell/`,
`page/`, `ui/`, `form/`, then `auth/`, `errors/`, `airports/`, `flights/`,
`bids/`, `livemap/`, `ofp/`, `pireps/`, `profile/`, `tours/`, `dev/`.

The prefix resolves through the active theme's view paths. A theme that
`extends: airside` overrides a component by adding the same file under its own
`components/` (verified 2026-10-31).

To eject a TallStackUI component, a theme adds
`vendor/ts-ui/components/<name>/main.blade.php`. Copy the file from
`vendor/tallstackui/tallstackui/src/resources/views/components/`. Laravel
registers the `resources/views/vendor/ts-ui` override path only if that directory
exists at boot, so phpvms ships it empty (with a `.gitkeep`). phpvms then maps it
to each theme's `vendor/`. Do not delete that directory.

airside ejects two components. The first is
`vendor/ts-ui/components/slide/main.blade.php`. It is TallStackUI's slide with `role="dialog"`, `aria-modal`, an `aria-labelledby`
title, a focus trap over the whole panel (close button, body and footer) and an
`aria-label` on the close button. `<x-vms::ui.drawer>` wraps it.

The second is `vendor/ts-ui/components/tab/main.blade.php` and `items.blade.php`.
Each tab gets an id and `aria-controls`, each panel an id and `aria-labelledby`,
tabs use a roving `tabindex`, and Left, Right, Home and End move the selection
and focus.

## Styling rules

These apply to every view, component and `TallStackUi::customize()` block in airside.

1. **One place for the look.** Colour, background, border, shadow, radius and
   font family go in `resources/css/airside.css`, inside `@layer components`, on a
   `vms-*` hook. They never go in Blade markup, in `customize()` blocks or in a
   `style=""` attribute.
2. **What markup may carry.** Layout utilities (grid, flex, gap, padding, margin,
   sizing, position, responsive variants) and type utilities (size, weight,
   tracking, leading, `tabular-nums`). `customize()` blocks add hook classes plus
   layout utilities only.
3. **Colour with `@apply` and the palette.** Write colours as palette utilities
   through `@apply`, and always give the dark pair in the same rule:
   - neutrals: `white`/`gray-*` in light, `dark-*` in dark;
   - brand: `primary-600` in light, `primary-400` in dark;
   - meaning: amber for unavailable or warning, red for errors, green only where
     the state is positive and needs saying.
   No hex, `rgb()` or `oklch()` literals in rules, and no Tailwind hues such as `sky-*` or `blue-*` for
   the brand.
4. **Tokens for shared values.** Use the `--vms-*` tokens when a value must follow
   the brand, or be changed by a site in one place:
   - `var(--vms-brand)` and `var(--vms-brand-soft)`: brand colour where no utility fits
     (SVG `fill`/`stroke`, `color-mix()`);
   - `var(--vms-radius-md|lg|xl)`: field, card and page-block radius;
   - `var(--vms-gutter)`: page and shell spacing.
   Tokens are defined only in `@layer base` (`:root` and `.dark`) in `airside.css`.
   Add a new token only for a value a site would want to change everywhere at once.
5. **Plain properties where `@apply` does not fit.** Use plain CSS for a token
   (`border-radius: var(--vms-radius-lg)`), and for properties with no utility
   (`paint-order`, custom transitions). Keep palette colours on `@apply`.
6. **The scheme is runtime.** The `primary-*` and `dark-*` ramps come from the
   saved colour scheme. The `@theme` block in `airside.css` is only the build
   fallback: do not edit it to restyle a page, and do not copy its values into
   rules.
7. **State from attributes.** Style state from ARIA or `data-*` attributes
   (`[aria-current="true"]`, `[data-availability="unavailable"]`), not from state
   classes.
8. **No `!important`.** If a rule loses, fix the source of the rule that beats it:
   - shared components set their fallback look with SVG presentation attributes or
     `:where()`;
   - TallStackUI defaults change through `customize()`.
9. **Type.** Manrope through `--font-sans` for text, with `tabular-nums` for times
   and numbers. Mono (`font-mono`) only where fixed-width text helps reading:
   METARs and other raw weather or route strings, and ICAO codes shown as codes
   (in a pill, a list or a strip). It is not for headlines, idents in a title or
   general numbers. The thin serif (`--font-serif`) is only for the brand word in
   a page title.
10. **Document every hook.** Each new hook, state, slot or option goes in
    `docs/theme-hooks.md` in the same change. Remove the entry when the hook goes.
11. **Custom CSS is different.** A site's Custom CSS box is plain, unlayered CSS
    with no `@apply`, so it beats these rules (see below).

## Shipped look: `airside.css`

Hook rules go in `@layer components`, written with `@apply` and TallStackUI's
palette (`gray-*` for light, `dark-*` for dark, `primary-*` for the brand).

```css
@layer components {
    .vms-auth-card {
        @apply overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-dark-700 dark:bg-dark-800;
    }

    .vms-auth-card-title {
        @apply text-lg font-semibold text-gray-900 dark:text-dark-50;
    }

    .vms-auth-card-footer {
        @apply border-t border-gray-200 bg-gray-50 text-[13px] dark:border-dark-700 dark:bg-dark-900;
    }

    .vms-ofp-tab {
        @apply border-b-2 border-transparent text-gray-500 hover:text-gray-900 dark:text-dark-400 dark:hover:text-dark-100;
    }

    .vms-ofp-tab[aria-selected="true"] {
        @apply border-primary-500 text-primary-600 dark:text-primary-400;
    }

    .vms-live-map-widget {
        width: var(--widget-width, 100%);
    }

    /* A theme option: the attribute is set on <html> when the admin picks it. */
    .vms-page {
        @apply mx-auto w-full max-w-7xl;
    }

    [data-airside-width="full"] .vms-page {
        @apply max-w-none;
    }
}
```

TallStackUI components render with TallStackUI's own classes. The provider's
`TallStackUi::customize()` blocks add `vms-*` hook classes to them (layout
utilities only), and `airside.css` colours those hooks with `@apply` in
`@layer components`. Custom CSS overrides them.

## Custom CSS

Custom CSS (the Custom CSS box on the Theme page) is plain CSS. Tailwind does not
process it, so `@apply` and Tailwind class names do nothing here. Use CSS
properties and the theme's variables (`var(--color-primary-500)`,
`var(--color-dark-800)` and so on). It is unlayered, so it beats the
`@layer components` hook rules without `!important`.

The build entry is `resources/css/styles.css`: it imports the fonts, TallStackUI
and `airside.css`. Edit it to build on `airside.css`, or to replace it.

```css
/* Square, borderless auth card with a soft shadow. */
.vms-auth-card {
    border: 0;
    border-radius: 0;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
}

/* Darker card background in dark mode. */
.dark .vms-auth-card {
    background: var(--color-dark-900);
}

/* Selected OFP tab in the secondary colour. */
.vms-ofp-tab[aria-selected="true"] {
    border-color: var(--color-secondary-500);
    color: var(--color-secondary-600);
}

/* Half-width live map, whatever the widget setting says. */
.vms-live-map-widget {
    width: 50%;
}

/* Always full width, without the theme option. */
.vms-page {
    max-width: none;
}
```

### Why Custom CSS wins

The built `airside.css` puts Tailwind's output in cascade layers (`theme`,
`base`, `components`, `utilities`). Custom CSS is emitted after it, outside any
layer. For normal (not `!important`) declarations, unlayered CSS beats layered
CSS whatever the selector specificity, so `.vms-auth-card { border: 0 }` wins
with no `!important`.

Against other unlayered CSS, such as TallStackUI's plugin stylesheets (tooltip,
scrollbars), the usual rules apply: higher specificity wins, then the later rule.

### What Custom CSS cannot override

- **Important utilities**, such as TallStackUI's `shadow-none!`. TallStackUI
  puts them in option blocks (`shadowless`, disabled, input prefix and suffix)
  so the option beats the base classes, since Tailwind orders utilities itself
  rather than by their order in the markup. They apply only when that option is
  on. Even `!important` in Custom CSS loses to them: for important declarations,
  layered CSS beats unlayered.
- **Inline properties**, without `!important`. `.vms-live-map-widget { --widget-width: 50% }`
  does not work, because the inline `style="--widget-width: …"` sets the variable
  on the element itself. Override the property instead:
  `.vms-live-map-widget { width: 50% }`. JavaScript that writes `element.style`
  counts as inline too, so islands pass sizes through variables as well.
- **A layout utility left inline** beats the hook rule in `airside.css`, but not
  Custom CSS. This is why the markup keeps layout only.

## Decisions

Recorded as they are made. Newest last.

| Date | Decision | Why |
| --- | --- | --- |
| 2026-10-31 | Superseded by the `vms` row below: Hooks start with `pv-` and go on blocks a site would plausibly restyle, not every element. | `pv-` matches skylight and avoids collisions with utilities and addons. |
| 2026-10-31 | Scope hooks on pages and component roots (`vms-flight-manifest`); pattern hooks on repeated parts (`vms-table-row`); combine them to target one place. | Half the class names of per-block names, and one rule restyles every table; still works when a component is reused on another page. |
| 2026-10-31 | Markup keeps layout utilities only; the look goes in `airside.css` under `@layer components`. | One place to change the look; inline look utilities would beat the hook rule. |
| 2026-10-31 | State is styled from ARIA or `data-state` attributes, not state classes. | One source of truth that also serves accessibility; the Radix and Headless UI convention. |
| 2026-10-31 | No inline styles. Data values reach CSS as variables. | Inline properties cannot be overridden by Custom CSS. |
| 2026-10-31 | Custom CSS is plain, unlayered CSS. | It applies at runtime with no rebuild and beats every layered rule. |
| 2026-10-31 | Theme options will set attributes on `<html>` that `airside.css` styles. | Sites change layout without code or a copied theme, as Discourse themes do. |
| 2026-10-31 | The old kit token classes (`border-line`, `bg-panel`, `text-ink` and the rest) are removed. | Nothing defines them; their borders fell back to the text colour. |
| 2026-10-31 | `@source` for PHP is limited to `app/Providers/*.php`. | Matches TallStackUI's soft customization docs; no surprise classes from other PHP. |
| 2026-10-31 | Clean baseline first: remove the old token classes and add nothing; TallStackUI components render vanilla. Hooks are added after seeing the baseline, starting with the flight listing page, then the OFP tabs. | Decide what to style from a clean, known starting point. |
| 2026-10-31 | No hooks inside TallStackUI components for now; their `!` option classes are left as they are. | They are TallStackUI's; touch them only if the baseline shows a need. |
| 2026-10-31 | Hooks and components use `vms` (`vms-*` classes, `<x-vms::…>` tags), not the theme name. | A copied or child theme keeps every template and hook unchanged. |
| 2026-10-31 | Views use TallStackUI directly; own components only where TallStackUI has none, or to eject one. Thin wrappers removed (alert, card, field, loading, back-link, stat, state-badge, confirm). | One component per job; no second API to learn or keep in step with TallStackUI. |
| 2026-10-31 | Confirmations use TallStackUI's dialog, opened from the button with `$tsui.interaction('dialog')`; prompt text sits in `data-*` attributes. | No Livewire changes; the text stays plain HTML. |
| 2026-11-04 | Styling rules: the look lives in `airside.css` `@layer components` on hooks, with colour by `@apply` in light/dark pairs, `--vms-*` tokens for brand, radius and gutter, no colour literals, no `!important`, mono only for METARs and codes. | One place to restyle; dark mode and saved colour schemes keep working; Custom CSS can always override. |
