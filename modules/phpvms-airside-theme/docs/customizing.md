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
[hooks.md](hooks.md). The examples below include hooks that are still being
added (`ofp-tab`, `live-map-widget`, `airside-page`); hooks.md lists what exists.

## Hooks in markup

The hook class is the component's name. Parts use the name as a prefix. The
markup keeps layout utilities only (grid, flex, gap, padding); colour, border,
background, radius and shadow belong in CSS.

```blade
<section {{ $attributes->class('auth-card w-full') }}>
    <div class="grid gap-4 p-6">
        <h1 class="auth-card-title">{{ $title }}</h1>
        {{ $slot }}
    </div>
    <footer class="auth-card-footer flex justify-between gap-2 px-6 py-3">{{ $footer }}</footer>
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
<div class="live-map-widget" style="--widget-width: {{ $config['width'] }}">
```

## Shipped look: `airside.css`

Hook rules go in `@layer components`, written with `@apply` and TallStackUI's
palette (`gray-*` for light, `dark-*` for dark, `primary-*` for the brand).

```css
@layer components {
    .auth-card {
        @apply overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-dark-700 dark:bg-dark-800;
    }

    .auth-card-title {
        @apply text-lg font-semibold text-gray-900 dark:text-dark-50;
    }

    .auth-card-footer {
        @apply border-t border-gray-200 bg-gray-50 text-[13px] dark:border-dark-700 dark:bg-dark-900;
    }

    .ofp-tab {
        @apply border-b-2 border-transparent text-gray-500 hover:text-gray-900 dark:text-dark-400 dark:hover:text-dark-100;
    }

    .ofp-tab[aria-selected="true"] {
        @apply border-primary-500 text-primary-600 dark:text-primary-400;
    }

    .live-map-widget {
        width: var(--widget-width, 100%);
    }

    /* A theme option: the attribute is set on <html> when the admin picks it. */
    .airside-page {
        @apply mx-auto w-full max-w-7xl;
    }

    [data-airside-width="full"] .airside-page {
        @apply max-w-none;
    }
}
```

TallStackUI components are styled the same way. `AirsideThemeServiceProvider`
uses `TallStackUi::customize()` only to swap each block's look classes for a hook
class (`ts-card`, `ts-card-header`, `ts-card-body`, `ts-card-footer`), and the
look lives in `airside.css` like any other hook.

## Custom CSS: `styles.css`

Custom CSS is plain CSS. Tailwind is not available at runtime, so `@apply` and
Tailwind class names do nothing here. Use CSS properties and the theme's
variables (`var(--color-primary-500)`, `var(--color-dark-800)` and so on).

```css
/* Square, borderless auth card with a soft shadow. */
.auth-card {
    border: 0;
    border-radius: 0;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
}

/* Darker card background in dark mode. */
.dark .auth-card {
    background: var(--color-dark-900);
}

/* Selected OFP tab in the secondary colour. */
.ofp-tab[aria-selected="true"] {
    border-color: var(--color-secondary-500);
    color: var(--color-secondary-600);
}

/* Half-width live map, whatever the widget setting says. */
.live-map-widget {
    width: 50%;
}

/* Always full width, without the theme option. */
.airside-page {
    max-width: none;
}
```

### Why Custom CSS always wins

The built `airside.css` puts all of Tailwind's output in cascade layers (`theme`,
`base`, `components`, `utilities`). Custom CSS is emitted after it, outside any
layer, and unlayered CSS beats layered CSS whatever the selector specificity. So
`.auth-card { border: 0 }` wins with no `!important`.

### What Custom CSS cannot override

- **Important utilities**, such as TallStackUI's `shadow-none!`. TallStackUI
  puts them in option blocks (`shadowless`, disabled, input prefix and suffix)
  so the option beats the base classes, since Tailwind orders utilities itself
  rather than by their order in the markup. They apply only when that option is
  on. Even `!important` in Custom CSS loses to them: for important declarations,
  layered CSS beats unlayered. Where a component airside uses has one, the
  provider maps that block to a hook (such as `ts-card-shadowless`) instead.
- **Inline properties.** `.live-map-widget { --widget-width: 50% }` does not
  work, because the inline `style="--widget-width: …"` sets the variable on the
  element itself. Override the property instead: `.live-map-widget { width: 50% }`.
- **A layout utility left inline** beats the hook rule in `airside.css`, but not
  Custom CSS. This is why the markup keeps layout only.

## Decisions

Recorded as they are made. Newest last.

| Date | Decision | Why |
| --- | --- | --- |
| 2026-10-31 | Hook class per component and per page block, named after it; parts use the name as a prefix. | One stable name for theme authors and admins to target. |
| 2026-10-31 | Markup keeps layout utilities only; the look goes in `airside.css` under `@layer components`. | One place to change the look; inline look utilities would beat the hook rule. |
| 2026-10-31 | State is styled from ARIA or `data-state` attributes, not state classes. | One source of truth that also serves accessibility; the Radix and Headless UI convention. |
| 2026-10-31 | TallStackUI components get hook classes through `TallStackUi::customize()`; their look is in CSS. | Styling in CSS is more common and easier to customize than PHP class blocks. |
| 2026-10-31 | No inline styles. Data values reach CSS as variables. | Inline properties cannot be overridden by Custom CSS. |
| 2026-10-31 | Custom CSS is plain, unlayered CSS; its starting text is `resources/customize/styles.css`. | It applies at runtime with no rebuild and beats every layered rule. |
| 2026-10-31 | Theme options will set attributes on `<html>` that `airside.css` styles. | Sites change layout without code or a copied theme, as Discourse themes do. |
| 2026-10-31 | The old kit token classes (`border-line`, `bg-panel`, `text-ink` and the rest) are removed. | Nothing defines them; their borders fell back to the text colour. |
| 2026-10-31 | `@source` for PHP is limited to `app/Providers/*.php`. | Matches TallStackUI's soft customization docs; no surprise classes from other PHP. |
