# Customizing airside

airside is styled so a site can change how it looks without touching code or
copying the theme. There are three layers, each one overriding the one before:

1. **Shipped look.** `resources/css/airside.css`, built with Tailwind. Changing it
   needs a rebuild, so only theme authors edit it.
2. **Theme options.** Settings an admin picks, such as full-width pages. Each
   option sets an attribute on `<html>` that `airside.css` already styles.
   (Planned; see the `theme-customization` openspec change.)
3. **Custom CSS.** Plain CSS an admin writes on the Content > Theme page. It is
   saved to the database and applies on the next page load, with no rebuild.
   Its starting text is `resources/customize/styles.css`.

Every element worth styling carries a **hook**: a stable class name, an ARIA or
data attribute for its state, and CSS variables for values that come from data.
Both `airside.css` and Custom CSS target the same hooks. The full list is in
[theme-hooks.md](theme-hooks.md). The examples below include hooks that do not
exist yet (`pv-ofp-tab`, `pv-live-map-widget`, `pv-page`); theme-hooks.md lists
what exists.

## Hooks in markup

Hooks start with `pv-`, the same prefix skylight uses. They go on blocks a site
would plausibly restyle, not on every element. There are two kinds:

- **Scope hooks** on each page and each component root, named after it:
  `pv-flights`, `pv-flight-manifest`, `pv-auth-card`.
- **Pattern hooks** on repeated parts, named the same everywhere: `pv-table`,
  `pv-table-row`, `pv-card`. One rule styles every instance.

Combine them to target one place: `.pv-flight-manifest .pv-table-row`. Add a
block-specific name (`pv-flight-manifest-row`) only when one scope holds two of
the same pattern that must look different, or for a part with no pattern. The
markup keeps layout utilities only (grid, flex, gap, padding); colour, border,
background, radius and shadow belong in CSS.

```blade
<section {{ $attributes->class('pv-auth-card w-full') }}>
    <div class="grid gap-4 p-6">
        <h1 class="pv-auth-card-title">{{ $title }}</h1>
        {{ $slot }}
    </div>
    <footer class="pv-auth-card-footer flex justify-between gap-2 px-6 py-3">{{ $footer }}</footer>
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
<div class="pv-live-map-widget" style="--widget-width: {{ $config['width'] }}">
```

## Shipped look: `airside.css`

Hook rules go in `@layer components`, written with `@apply` and TallStackUI's
palette (`gray-*` for light, `dark-*` for dark, `primary-*` for the brand).

```css
@layer components {
    .pv-auth-card {
        @apply overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-dark-700 dark:bg-dark-800;
    }

    .pv-auth-card-title {
        @apply text-lg font-semibold text-gray-900 dark:text-dark-50;
    }

    .pv-auth-card-footer {
        @apply border-t border-gray-200 bg-gray-50 text-[13px] dark:border-dark-700 dark:bg-dark-900;
    }

    .pv-ofp-tab {
        @apply border-b-2 border-transparent text-gray-500 hover:text-gray-900 dark:text-dark-400 dark:hover:text-dark-100;
    }

    .pv-ofp-tab[aria-selected="true"] {
        @apply border-primary-500 text-primary-600 dark:text-primary-400;
    }

    .pv-live-map-widget {
        width: var(--widget-width, 100%);
    }

    /* A theme option: the attribute is set on <html> when the admin picks it. */
    .pv-page {
        @apply mx-auto w-full max-w-7xl;
    }

    [data-airside-width="full"] .pv-page {
        @apply max-w-none;
    }
}
```

TallStackUI components render with TallStackUI's own classes. Whether any of
them need hooks is decided after seeing the vanilla baseline (see Decisions).

## Custom CSS: `styles.css`

Custom CSS is plain CSS. Tailwind is not available at runtime, so `@apply` and
Tailwind class names do nothing here. Use CSS properties and the theme's
variables (`var(--color-primary-500)`, `var(--color-dark-800)` and so on).

```css
/* Square, borderless auth card with a soft shadow. */
.pv-auth-card {
    border: 0;
    border-radius: 0;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
}

/* Darker card background in dark mode. */
.dark .pv-auth-card {
    background: var(--color-dark-900);
}

/* Selected OFP tab in the secondary colour. */
.pv-ofp-tab[aria-selected="true"] {
    border-color: var(--color-secondary-500);
    color: var(--color-secondary-600);
}

/* Half-width live map, whatever the widget setting says. */
.pv-live-map-widget {
    width: 50%;
}

/* Always full width, without the theme option. */
.pv-page {
    max-width: none;
}
```

### Why Custom CSS wins

The built `airside.css` puts Tailwind's output in cascade layers (`theme`,
`base`, `components`, `utilities`). Custom CSS is emitted after it, outside any
layer. For normal (not `!important`) declarations, unlayered CSS beats layered
CSS whatever the selector specificity, so `.pv-auth-card { border: 0 }` wins
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
- **Inline properties**, without `!important`. `.pv-live-map-widget { --widget-width: 50% }`
  does not work, because the inline `style="--widget-width: …"` sets the variable
  on the element itself. Override the property instead:
  `.pv-live-map-widget { width: 50% }`. JavaScript that writes `element.style`
  counts as inline too, so islands pass sizes through variables as well.
- **A layout utility left inline** beats the hook rule in `airside.css`, but not
  Custom CSS. This is why the markup keeps layout only.

## Decisions

Recorded as they are made. Newest last.

| Date | Decision | Why |
| --- | --- | --- |
| 2026-10-31 | Hooks start with `pv-` and go on blocks a site would plausibly restyle, not every element. | `pv-` matches skylight and avoids collisions with utilities and addons. |
| 2026-10-31 | Scope hooks on pages and component roots (`pv-flight-manifest`); pattern hooks on repeated parts (`pv-table-row`); combine them to target one place. | Half the class names of per-block names, and one rule restyles every table; still works when a component is reused on another page. |
| 2026-10-31 | Markup keeps layout utilities only; the look goes in `airside.css` under `@layer components`. | One place to change the look; inline look utilities would beat the hook rule. |
| 2026-10-31 | State is styled from ARIA or `data-state` attributes, not state classes. | One source of truth that also serves accessibility; the Radix and Headless UI convention. |
| 2026-10-31 | No inline styles. Data values reach CSS as variables. | Inline properties cannot be overridden by Custom CSS. |
| 2026-10-31 | Custom CSS is plain, unlayered CSS; its starting text is `resources/customize/styles.css`. | It applies at runtime with no rebuild and beats every layered rule. |
| 2026-10-31 | Theme options will set attributes on `<html>` that `airside.css` styles. | Sites change layout without code or a copied theme, as Discourse themes do. |
| 2026-10-31 | The old kit token classes (`border-line`, `bg-panel`, `text-ink` and the rest) are removed. | Nothing defines them; their borders fell back to the text colour. |
| 2026-10-31 | `@source` for PHP is limited to `app/Providers/*.php`. | Matches TallStackUI's soft customization docs; no surprise classes from other PHP. |
| 2026-10-31 | Clean baseline first: remove the old token classes and add nothing; TallStackUI components render vanilla. Hooks are added after seeing the baseline, starting with the flight listing page, then the OFP tabs. | Decide what to style from a clean, known starting point. |
| 2026-10-31 | No hooks inside TallStackUI components for now; their `!` option classes are left as they are. | They are TallStackUI's; touch them only if the baseline shows a need. |
