# airside hooks

Every hook airside exposes for styling. See [customizing.md](customizing.md) for
how hooks work and how to override them.

Hook classes start with `vms-`. Add an entry in the same change that adds a hook.
Each entry lists:

- **View:** the file the hook is in.
- **Element:** what the class sits on.
- **Parts:** child hooks (`vms-<name>-<part>`).
- **State:** the attributes and values to select on, e.g. `[aria-selected="true"]`.
- **Variables:** none.
- **Stability:** `stable` (a public contract: renaming it is a breaking change)
  or `draft` (may still change).

## Components

### `vms-auth-card`

The card every auth page sits in.

- **View:** `resources/views/components/auth/card.blade.php`
- **Element:** the root `<section>`.
- **Parts:** none yet.
- **State:** none.
- **Variables:** none.
- **Stability:** draft. The class is in the markup; the rule in `airside.css` is
  not written yet.

### `vms-landing`

The public landing layout's `<body>`: white page (dark: `dark-950`), full-height column so the footer sits at the bottom.

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<body>`.
- **Parts:** `vms-landing-nav`, `vms-landing-brand`, `vms-landing-links`, `vms-landing-account`, `vms-landing-footer`, `vms-pill`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-landing-nav`

The top nav bar of the landing layout.

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<header>`.
- **Parts:** `vms-landing-brand`, `vms-landing-links`, `vms-landing-account`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-landing-brand`

The logo and app name link, left of the nav.

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<a>`; its `<img>` is the logo (32px).
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-landing-links`

The top-level menu links. Below 960px it moves to its own row and scrolls sideways.

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<nav aria-label="Main">`.
- **Parts:** none (plain `<a>` children).
- **State:** `a[aria-current="page"]` on the active link.
- **Variables:** none.
- **Stability:** draft.

### `vms-landing-account`

The right side of the nav: sign in and register for guests, the user link for pilots.

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-pill`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-pill`

A rounded call-to-action link. Modifiers: `vms-pill--dark` (the primary scale: `--color-primary-600`, hover `--color-primary-700`) and `vms-pill--light` (translucent surface fill).

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<a>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--color-primary-600`, `--color-primary-700`, `--color-white` (colours).
- **Stability:** draft. `vms-pill--light` is used in the home hero; `vms-pill--lg` makes it larger.

### `vms-landing-footer`

The footer card of the landing layout: app name, copyright and the phpvms credit.

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<footer>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-radius-lg`, `--vms-gutter` (length).
- **Stability:** draft.

### `vms-landing-hero`

The home page hero card: a generated sky SVG fills it, the text sits at the bottom. Below 960px it is 560px tall.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<section>`; its direct `<svg>` is the sky.
- **Parts:** `vms-landing-hero-body`.
- **State:** none.
- **Variables:** `--vms-radius-xl`, `--vms-gutter` (length).
- **Stability:** draft.

### `vms-landing-hero-body`

The text block of the hero (padding 56px, 32px below 960px).

- **View:** `resources/views/home.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-landing-eyebrow`, `vms-landing-title`, `vms-landing-lead`, `vms-landing-actions`, `vms-pill--lg`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-landing-eyebrow`

The small uppercase line above the hero title.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-landing-title`

The hero `<h1>`. A child `<span>` is set in the serif face (`--font-serif`, weight 200).

- **View:** `resources/views/home.blade.php`
- **Element:** the `<h1>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--font-serif` (font).
- **Stability:** draft.

### `vms-landing-lead`

The hero intro paragraph.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-landing-actions`

The row of hero call-to-action links.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-pill`, `vms-pill--lg`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-pill--lg`

Larger padding for a `vms-pill`, used in the hero.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<a>`, together with `vms-pill`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-landing-stories`

The three-column row of story cards below the hero. One column below 960px.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<section>`.
- **Parts:** `vms-story`.
- **State:** none.
- **Variables:** `--vms-gutter` (length).
- **Stability:** draft.

### `vms-story`

One story card: label, heading and text.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<article>`.
- **Parts:** `vms-story-label`.
- **State:** none.
- **Variables:** `--vms-radius-lg` (length).
- **Stability:** draft.

### `vms-story-label`

The small uppercase label at the top of a story card.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-brand` (colour).
- **Stability:** draft.

### `vms-landing-news`

The latest news section of the home page (110px top padding, 72px below 960px).

- **View:** `resources/views/home.blade.php`
- **Element:** the `<section>`.
- **Parts:** `vms-section-head`, `vms-section-eyebrow`, `vms-section-title`, `vms-news-grid`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-section-head`

The heading row of a landing section.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-section-eyebrow`, `vms-section-title`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-section-eyebrow`

The small uppercase line above a section title.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-brand` (colour).
- **Stability:** draft.

### `vms-section-title`

A landing section title (48px, 36px below 960px).

- **View:** `resources/views/home.blade.php`
- **Element:** the `<h2>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-news-grid`

The three-column grid of news cards. One column below 960px.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-news`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-news`

One news card: date, title and summary. It is not a link.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<article>`; children are `<time>`, `<h4>` and `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-radius-lg` (length).
- **Stability:** draft.

### `vms-shell`

The signed-in layout's `<body>`: white page (dark: `dark-950`) and ink colour, set in `airside.css`.

- **View:** `resources/views/components/layouts/app.blade.php`
- **Element:** the `<body>`.
- **Parts:** `vms-shell-main`, `vms-shell-header`, `vms-shell-topbar`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-shell-main`

The page area of the signed-in layout (padding `--vms-gutter`). Set in `customizeShell()` of the theme provider.

- **View:** `resources/views/components/layouts/app.blade.php`
- **Element:** the `<main>` that TallStackUI's layout renders.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-gutter` (length).
- **Stability:** draft.

### `vms-shell-header`

The dispatch strip: a rounded bar with the airline, duty, METAR and clocks. Surface and hairline border (`gray-200`, dark: `dark-800` and `dark-700`) come from this hook. Set in `customizeShell()` of the theme provider.

- **View:** `resources/views/components/shell/header.blade.php`
- **Element:** the layout header `<div>`.
- **Parts:** `vms-dispatch-sep`.
- **State:** none.
- **Variables:** `--vms-gutter` (length).
- **Stability:** draft.

### `vms-shell-sidebar`

The sidebar panel, desktop and mobile (`desktop.wrapper.second` and `mobile.wrapper.fourth` of the TallStackUI sidebar, in `customizeShell()`). `bg-gray-100`, dark: `bg-dark-900`.

- **View:** `resources/views/components/shell/sidebar.blade.php`
- **Element:** the sidebar panel `<div>` rendered by TallStackUI.
- **Parts:** `vms-shell-item`, `vms-shell-item-current`, `vms-shell-icon`, `vms-shell-icon-muted`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-shell-item`

A sidebar group button or item: muted ink, a translucent white pill on hover. `vms-shell-item-current` is the current item: a solid white pill (dark: `dark-800`). `vms-shell-icon` is the icon colour of the header, group and item icons (`dark-600`, dark: `dark-300`); `vms-shell-icon-muted` is the group caret (`dark-400`). All are set in `customizeShell()`.

- **View:** `resources/views/components/shell/sidebar.blade.php`
- **Element:** the TallStackUI sidebar item or group button; the icon `<svg>`.
- **Parts:** `vms-shell-item-current`, `vms-shell-icon`, `vms-shell-icon-muted`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-widget-card`

The surface of a dashboard widget card (TallStackUI card scope `airside.widget`, in `customizeShell()`) and of a `vms-metric` tile: white, dark: `dark-800`, hairline border `gray-200`, dark: `dark-700`. `vms-widget-card-title` is the card header text.

- **View:** `resources/views/livewire/dashboard/dashboard-board.blade.php`
- **Element:** the card wrapper rendered by TallStackUI; the card title.
- **Parts:** `vms-widget-card-title`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-dispatch-sep`

The thin divider between the airline and the dispatch status. Hidden below 1024px with the status.

- **View:** `resources/views/components/shell/header.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-shell-brand`

The logo and app name link: the sidebar brand and the left end of the top bar.

- **View:** `resources/views/components/shell/brand.blade.php`
- **Element:** the `<a>`; its `<img>` is the logo (32px).
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-shell-topbar`

The nav row of the top bar layout (`layout` option `topbar`): brand left, menu centred, account right.

- **View:** `resources/views/components/shell/topbar.blade.php`
- **Element:** the `<header>`.
- **Parts:** `vms-shell-brand`, `vms-shell-links`, `vms-shell-account`.
- **State:** none.
- **Variables:** `--vms-gutter` (length).
- **Stability:** draft.

### `vms-shell-links`

The centred menu of the top bar. Hidden below 768px: the same entries open in a slide-over from the button at the left.

- **View:** `resources/views/components/shell/topbar.blade.php`
- **Element:** the `<nav aria-label="Main">`.
- **Parts:** `vms-shell-link`, `vms-shell-group`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-shell-link`

A top bar menu link, or the button of a group that opens a dropdown. In the slide-over it is a full-width row.

- **View:** `resources/views/components/shell/topbar-item.blade.php`
- **Element:** the `<a>` or `<button>`.
- **Parts:** none.
- **State:** `[aria-current="page"]` on the current page (for a group, when a child is current).
- **Variables:** none.
- **Stability:** draft.

### `vms-shell-group`

The small label of a group in the top bar's slide-over.

- **View:** `resources/views/components/shell/topbar-item.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-shell-account`

The account menu trigger: avatar, name, and ident · callsign. In the sidebar it is the footer button; in the top bar it sits at the right end.

- **View:** `resources/views/components/shell/pilot-menu.blade.php`
- **Element:** the `<button>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `clouds-bg`

The sky colours. A class on the `vms-sky` `<svg>` (added by `x-vms::sky`); `airside.css` styles its parts by class (`clouds-bg-top`, `-mid`, `-bottom` gradient stops, `-light` cloud lighting, `-glow`, `-wisps` and `-clouds` opacity): a day sky, and a night sky under `.dark .clouds-bg`. These are plain properties, not variables.

- **View:** `resources/views/components/sky.blade.php`
- **Element:** the `<svg>`.
- **Parts:** none.
- **State:** `.dark` ancestor.
- **Variables:** none.
- **Stability:** draft.

### `vms-sky`

The generated sky SVG, filling a positioned parent. Used by the home hero and the dashboard hero.

- **View:** `resources/views/components/sky.blade.php`
- **Element:** the `<svg>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-pilot-summary`

The dashboard hero: sky background, greeting and rank card.

- **View:** `resources/views/dashboard/index.blade.php`
- **Element:** the `<section>`.
- **Parts:** `vms-sky`, `vms-pilot-eyebrow`, `vms-pilot-greeting`, `vms-pilot-chips`, `vms-rank`.
- **State:** none.
- **Variables:** `--vms-radius-xl` (length).
- **Stability:** draft.

### `vms-pilot-eyebrow`

The small uppercase "Dashboard" line with the status badge, above the greeting.

- **View:** `resources/views/dashboard/index.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-pilot-badge`

The pilot's status pill in the eyebrow: a coloured dot and the state label. The colour follows the pilot state.

- **View:** `resources/views/dashboard/index.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-pilot-greeting`

The dashboard `<h1>`: "Good morning/afternoon/evening," and the pilot's name in the serif face (`--font-serif`, weight 200) in the child `<span>`. The time of day follows the pilot's timezone, else the app timezone.

- **View:** `resources/views/dashboard/index.blade.php`
- **Element:** the `<h1>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--font-serif` (font).
- **Stability:** draft.

### `vms-pilot-chips`

The ident, callsign and current airport chips under the greeting.

- **View:** `resources/views/dashboard/index.blade.php`
- **Element:** the `<p>`; each chip is a `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-rank`

The rank progress card in the hero: hours, a native `<progress>` and the hours to promotion.

- **View:** `resources/views/dashboard/index.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-metrics`

The row of seven pilot statistics. 2 columns on small screens, 4 from 640px, 7 from 1200px.

- **View:** `resources/views/dashboard/index.blade.php`
- **Element:** the `<dl>`.
- **Parts:** `vms-metric`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-metric`

One statistic: a label (`<dt>`) and a large number with small muted units (`<dd>`, units in `<small>`).

- **View:** `resources/views/dashboard/index.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-radius-lg` (length).
- **Stability:** draft.

### `vms-board-toolbar`

The row above the board: title, and the Add widget, Reset and Customize buttons. The buttons are TallStackUI buttons: Customize is the primary colour, Add widget the `light` variant.

- **View:** `resources/views/livewire/dashboard/dashboard-board.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-board-title`, `vms-pill`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-board-title`

The "Your board" heading.

- **View:** `resources/views/livewire/dashboard/dashboard-board.blade.php`
- **Element:** the `<h2>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-board`

The grid of widget zones: the main zone and a 360px side zone from 1100px.

- **View:** `resources/views/livewire/dashboard/dashboard-board.blade.php`
- **Element:** the `<div>`; zones are `[data-zone]`.
- **Parts:** `vms-widget`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-widget`

One widget frame. In edit mode it has a dashed outline. The card inside uses the TallStackUI scope `airside.widget` (see `customizeShell()`).

- **View:** `resources/views/livewire/dashboard/dashboard-board.blade.php`
- **Element:** the `<div data-widget>`.
- **Parts:** `vms-widget-icon`.
- **State:** none.
- **Variables:** `--vms-radius-lg` (length).
- **Stability:** draft.

### `vms-widget-icon`

The round icon at the left of a widget title.

- **View:** `resources/views/livewire/dashboard/dashboard-board.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-brand`, `--vms-brand-soft` (colours).
- **Stability:** draft.

### `vms-route-map`

The Nav display map container (300px).

- **View:** `resources/views/livewire/dashboard/route-widget.blade.php`
- **Element:** the `<div data-airside-island>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-radius-md` (length).
- **Stability:** draft.

### `vms-route-readout`

The route and the TRK and DIST readout under the map.

- **View:** `resources/views/livewire/dashboard/route-widget.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-brand` (colours).
- **Stability:** draft.

### `vms-flying`

The "pilots flying now" count at the top of the Activity widget.

- **View:** `resources/views/livewire/dashboard/activity-feed.blade.php`
- **Element:** the `<header>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-news`

The list of items in the Latest news widget.

- **View:** `resources/views/livewire/dashboard/latest-news.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

## Variables

The tokens are declared in `@layer base` in `resources/css/airside.css`
(`:root` for light, `.dark` for dark). Colours are not tokens: parts set them
with `vms-*` hook classes (`@apply` in `@layer components`), and the hero text
on the sky uses literal colours in its own rules (`vms-landing-*`, `vms-pilot-*`,
`vms-rank`). Custom CSS overrides the tokens and the hooks.

Brand and neutral colours come from the saved colour scheme (the Colour scheme
select on the Theme page, saved as `color_scheme`), which sets `--color-primary-*`,
`--color-secondary-*` and `--color-dark-*` at runtime. The `@theme` block in
`airside.css` is the build-time fallback (the phpvms scheme).

| Variable | Unit | Light default |
| --- | --- | --- |
| `--vms-brand` | colour | `--color-primary-600` (dark: `--color-primary-400`) |
| `--vms-brand-soft` | colour | `--color-primary-600` at 12% over white (dark: `--color-primary-950`) |
| `--vms-radius-xl` | length | `28px` |
| `--vms-radius-lg` | length | `22px` |
| `--vms-radius-md` | length | `12px` |
| `--vms-gutter` | length | `20px` |
