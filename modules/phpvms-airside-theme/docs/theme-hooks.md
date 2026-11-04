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
- **Parts:** `vms-widget-card-title`, `vms-widget-card-body` (the card body).
- **State:** `vms-widget--bare` on the widget cell, for a catalog entry with `bare` set (Next flight) outside Customize mode: no header and no body padding (the card keeps its border and surface) (`in-[.vms-widget--bare]:p-0` on the body block).
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

One widget frame. In edit mode it has a dashed outline and can be dragged by its handle (Livewire `wire:sort`). The card inside uses the TallStackUI scope `airside.widget` (see `customizeShell()`).

- **View:** `resources/views/livewire/dashboard/dashboard-board.blade.php`
- **Element:** the `<div data-widget>`.
- **Parts:** `vms-widget-icon`.
- **State:** `.sortable-ghost` (the drop preview while dragging: dashed brand outline, faint brand background, faded content); `.sortable-chosen` (the lifted item: shadow, grabbing cursor).
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

### `vms-next-flight`

The Next flight widget when a bid exists.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the root `<div>`.
- **Parts:** `vms-next-flight-route`, `vms-next-flight-pills`, `vms-next-flight-metar`, `vms-next-flight-stats`, `vms-next-flight-actions`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-route`

The "DEP → ARR" headline of the Next flight widget.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the `<h4>`.
- **Parts:** `vms-next-flight-arrow`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-arrow`

The arrow in the Next flight headline, muted.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-pills`

The callsign, flight type and route code pills of the Next flight widget. Each pill also carries `vms-flight-pill`.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the pills `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-metar`

The departure weather card in the Next flight widget (the `airport-weather` component, loaded lazily).

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the wrapping `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-stats`

The stat strip of the Next flight widget: block time, distance and briefing.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the `<dl>`.
- **Parts:** `vms-next-flight-stat`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-stat`

One stat in the Next flight strip: icon, label and value.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** each stat `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-actions`

The buttons of the Next flight widget: Start flight planning and Details.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-empty`

The Next flight widget when the pilot has no bid: "Ready to fly", the last flight and Find a flight.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the root `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flights`

The flights list page root.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the page `<div>`.
- **Parts:** `vms-flights-header`, `vms-flights-title`, `vms-flights-count`, `vms-flight-filters`, `vms-flight-list`, `vms-flight-pager`, `vms-flights-empty`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flights-header`

The flights list header: title and active-filter count.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<header>`.
- **Parts:** `vms-flights-title`, `vms-flights-count`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flights-title`

"Find a flight." The word "flight." is a `<span>` set in thin serif in the brand colour.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<h1>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flights-count`

The "N active" filter count.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-filters`

The filter surface: chips, the Filters toggle and the form.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<section>`.
- **Parts:** `vms-flight-chips`, `vms-flight-filters-form`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-filters-form`

The 17-field filter form.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<form>`.
- **Parts:** `vms-field`, `vms-flight-filters-actions`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-filters-actions`

The Apply and Clear buttons row.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-field`

A label with a native input or select. Style the control through `.vms-field input` and `.vms-field select`.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<label>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-chips`

The row of active-filter chips.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-flight-chip`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-chip`

One active-filter chip.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-list`

The list of flight cards.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<ul>`.
- **Parts:** `vms-flight-card`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-card`

One flight. A click or Enter/Space opens the assignment drawer on its details step. The card whose drawer is open is marked `aria-current="true"`.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<li>`.
- **Parts:** `vms-flight-summary`, `vms-flight-route`, `vms-flight-buttons`, `vms-flight-row-actions`, `vms-flight-reason`.
- **State:** `[data-availability="available|unavailable"]`, `[aria-current="true"]`.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-summary`

Airline logo and flight information of a card: callsign, airline name and the pills. Reusable as `<x-vms::flights.summary>`.

- **View:** `resources/views/components/flights/summary.blade.php`
- **Element:** the root `<div>`.
- **Parts:** `vms-flight-summary-logo`, `vms-flight-summary-text`, `vms-flight-pills`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-summary-logo`

The rounded square with the airline logo, or the first two letters of the airline name.

- **View:** `resources/views/components/flights/summary.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-summary-text`

The stack to the right of the logo: callsign, airline name, pills.

- **View:** `resources/views/components/flights/summary.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-flight-pills`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-route`

Departure and arrival stops joined by the leg.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-flight-stop`, `vms-flight-leg`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-stop`

One end of the route: the ICAO on top, the scheduled time under it.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-leg`

The middle of the route: the line with the plane, block time and distance as pills on it.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-flight-line`, `vms-flight-plane`, `vms-flight-leg-pill`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-leg-pill`

A small pill on the line of a card: block time or distance.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-buttons`

The stacked buttons of a card: Bid on flight, View bid or the disabled reason, then Details.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-flight-details`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-details`

The Details button on a card; it links to the flight page.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<a>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-pills`

The row of pills in a flight summary: flight type, route code and the status.

- **View:** `resources/views/components/flights/summary.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-flight-pill`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-reason`

Why a flight is unavailable. Also used in the drawer details step.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php, resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-row-actions`

Wraps the `flights.row.actions` slot on a card.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-pill`

A small pill. Variants: `vms-flight-pill--bid` (On your bid) and `vms-flight-pill--off` (Unavailable). Also used for the flight type in the drawer details step.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php, resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-line`

The thin line between the stops.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<i>`.
- **Parts:** `vms-flight-plane`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-plane`

The plane icon centred on the line.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<span>` around the icon.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-pager`

The Previous / Page X of Y / Next bar.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the `<nav>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flights-empty`

The "No flights found" empty state.

- **View:** `resources/views/livewire/flights/flight-manifest.blade.php`
- **Element:** the empty-state `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-flight-detail`

The details step of the assignment drawer.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-route-tile`, `vms-detail-title`, `vms-detail-facts`, `vms-detail-actions`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-detail-facts`

The six facts of the details step.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<dl>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-detail-actions`

The Bid on flight and Generate OFP buttons, the reason line and the `flights.detail.actions` slot.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-route-tile`

The dark tile at the top of the details step: label, two airports and a dashed arc.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-route-tile-label`, `vms-route-tile-arc`, `vms-route-tile-plane`, `vms-route-tile-stop`, `vms-route-tile-dot`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-route-tile-label`

The "{callsign} · Great circle" label of the tile.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-route-tile-arc`

The dashed arc of the tile.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-route-tile-plane`

The plane icon on the arc.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<span>` around the icon.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-route-tile-stop`

One airport on the tile: the dot and its ICAO. West is on the left when both coordinates are known.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-route-tile-dot`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-route-tile-dot`

The dot of an airport on the tile.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<span>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-detail-title`

The "{departure} to {arrival}" title of the details step. The "to" is a `<span>`.

- **View:** `resources/views/livewire/bids/assignment-drawer.blade.php`
- **Element:** the `<h3>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-location-card`

A place (ICAO and country) beside a map with one pin: `<x-vms::location-card>`. The Next flight widget uses it for the Ready to fly state; any page can.

- **View:** `resources/views/components/location-card.blade.php`
- **Element:** the root `<div>`.
- **Parts:** `vms-location-card-place`, `vms-location-card-icao`, `vms-location-card-country`, `vms-location-card-map`, `vms-location-card-actions`.
- **State:** the modifiers `vms-location-card--outline` or `vms-location-card--dotted` (the `variant` prop), and `vms-location-card--compact` or `vms-location-card--hero` (the `size` prop).
- **Variables:** none.
- **Stability:** draft.

Props: `icao`, `country` (strings or null); `lat`, `lon` (floats or null; the pin is drawn when both are set); `scope` (`country`, `continent` or `world`, default `country`; the map falls back country, then continent, then world); `variant` (`outline` or `dotted`, default `dotted`); `size` (`compact` or `hero`, default `compact`). Without an `icao` the card shows "—" and the world map with no pin.

Slots: the default slot holds action buttons laid over the map (rendered in `vms-location-card-actions`, omitted when empty); `aside` is optional and sits under the country (the Ready state puts the last flight block there); `details` is optional and sits after `aside`, for extra blocks (the Ready state holds the `dashboard.ready.details` outlet). The place column has its own `p-5.5` padding and is top-aligned; the actions row is inset `bottom-4 right-4` from the map corner.

The map is the core `<x-map.svg>`: land is `.land`, the focus country `.land--focus`, the airport `.map-pin`. In the dotted variant the land is filled with a dot pattern: the circles are `.map-dot`, and `.map-dot--focus` on the focus country's pattern. Colours are set under `vms-location-card` in `airside.css`: grey dots on land, a darker grey on the focus country, and the brand pin with a halo as the only colour. For a country with regions (the United States), the map shows the region holding the point: Alaska and Hawaii get their own maps, every other point the contiguous states.

### `vms-last-flight`

The last flight block in the Ready to fly state: label, route (arrow muted by `vms-last-flight-arrow`), flight time and distance. Omitted when the pilot has no last PIREP.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the `<div>` in the location card `aside` slot.
- **Parts:** `vms-last-flight-arrow`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-location-card-place`

The ICAO, country and `aside` slot column of the location card.

- **View:** `resources/views/components/location-card.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-location-card-icao`, `vms-location-card-country`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-location-card-icao`

The large ICAO code of the location card ("—" when unknown).

- **View:** `resources/views/components/location-card.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-location-card-country`

The country name under the ICAO, muted.

- **View:** `resources/views/components/location-card.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-location-card-map`

The map column of the location card.

- **View:** `resources/views/components/location-card.blade.php`
- **Element:** the `<div>`.
- **Parts:** `vms-location-card-actions`.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-location-card-actions`

The default slot of the location card, centred near the bottom of the map.

- **View:** `resources/views/components/location-card.blade.php`
- **Element:** the `<div>`.
- **Parts:** none.
- **State:** none.
- **Variables:** none.
- **Stability:** draft.

### `vms-next-flight-empty`

The Next flight widget when there is no bid: a `vms-location-card` for the pilot's current airport (else home airport). Its default slot holds the Find a flight button and the `dashboard.ready.actions` slot (context: `airport`, the `Airport` or null; `user`). Its `details` slot holds the `dashboard.ready.details` slot (context: `airport`, the `Airport` or null; `user`; `pirep`, the pilot's last `Pirep` or null). The `aside` slot holds the `vms-last-flight` block when the pilot has a last PIREP. The map follows the theme options `ready_map_scope` (`country`, `continent`, `world`; default `country`), `ready_map_style` (`outline`, `dotted`; default `dotted`) and `ready_map_size` (`compact`, `hero`; default `compact`); an unknown value falls back to the default.

- **View:** `resources/views/livewire/dashboard/next-flight.blade.php`
- **Element:** the wrapping `<div>` around the `vms-location-card`.
- **Parts:** `vms-location-card`.
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
