# airside hooks

Every hook airside exposes for styling. See [customizing.md](customizing.md) for
how hooks work and how to override them.

Hook classes start with `vms-`. Add an entry in the same change that adds a hook.
Each entry lists:

- **View:** the file the hook is in.
- **Element:** what the class sits on.
- **Parts:** child hooks (`vms-<name>-<part>`).
- **State:** the attributes and values to select on, e.g. `[aria-selected="true"]`.
- **Variables:** CSS variables, with their unit and default.
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

The public landing layout's `<body>`: canvas background, full-height column so the footer sits at the bottom.

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<body>`.
- **Parts:** `vms-landing-nav`, `vms-landing-brand`, `vms-landing-links`, `vms-landing-account`, `vms-landing-footer`, `vms-pill`.
- **State:** none.
- **Variables:** `--vms-canvas` (colour), `--vms-ink` (colour).
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
- **Variables:** `--vms-ink` (colour), `--vms-ink-2` (colour).
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

A rounded call-to-action link. Modifiers: `vms-pill--dark` (ink fill) and `vms-pill--light` (translucent surface fill).

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<a>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-ink`, `--vms-canvas`, `--vms-surface` (colours).
- **Stability:** draft. `vms-pill--light` is used in the home hero; `vms-pill--lg` makes it larger.

### `vms-landing-footer`

The footer card of the landing layout: app name, copyright and the phpvms credit.

- **View:** `resources/views/components/layouts/landing.blade.php`
- **Element:** the `<footer>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-surface`, `--vms-ink-2` (colours), `--vms-radius-lg`, `--vms-gutter` (length).
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
- **Variables:** `--vms-hero-ink-3` (colour).
- **Stability:** draft.

### `vms-landing-title`

The hero `<h1>`. A child `<span>` is set in the serif face (`--font-serif`, weight 200).

- **View:** `resources/views/home.blade.php`
- **Element:** the `<h1>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-hero-ink`, `--vms-hero-ink-2` (colours), `--font-serif` (font).
- **Stability:** draft.

### `vms-landing-lead`

The hero intro paragraph.

- **View:** `resources/views/home.blade.php`
- **Element:** the `<p>`.
- **Parts:** none.
- **State:** none.
- **Variables:** `--vms-hero-ink-3` (colour).
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
- **Variables:** `--vms-surface`, `--vms-ink-2` (colours), `--vms-radius-lg` (length).
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
- **Variables:** `--vms-surface`, `--vms-ink-2`, `--vms-ink-3` (colours), `--vms-radius-lg` (length).
- **Stability:** draft.

## Variables

The landing tokens are declared in `@layer base` in `resources/css/airside.css`
(`:root` for light, `.dark` for dark, built from the `--color-dark-*` scale). The `--vms-hero-ink*` tokens are the same in both modes: the hero text sits on the light sky.
Custom CSS overrides them.

| Variable | Unit | Light default |
| --- | --- | --- |
| `--vms-canvas` | colour | `#f3efea` |
| `--vms-surface` | colour | `#ffffff` |
| `--vms-ink` | colour | `#14213a` |
| `--vms-ink-2` | colour | `#5b6476` |
| `--vms-ink-3` | colour | `#8a92a2` |
| `--vms-brand` | colour | `--color-primary-600` (dark: `--color-primary-400`) |
| `--vms-hero-ink` | colour | `#14213a` |
| `--vms-hero-ink-2` | colour | `#5d7ea3` |
| `--vms-hero-ink-3` | colour | `#3e5470` |
| `--vms-radius-xl` | length | `28px` |
| `--vms-radius-lg` | length | `22px` |
| `--vms-radius-md` | length | `12px` |
| `--vms-gutter` | length | `20px` |
