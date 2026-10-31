{{--
    Live preview of the site under the theme picked in the select.

    The frame loads the pilot dashboard as the signed-in admin, with
    `?preview_theme=<name>` which SetActiveTheme honours for one request when
    the user has admin access. Nothing is saved until the form is submitted;
    changing the select only swaps the frame's URL. Navigating inside the
    frame drops the parameter, so the frame falls back to the saved theme.

    Height: the frame runs from its top edge to the bottom of the viewport,
    recomputed on resize. The page grid stretches its other column to match,
    and the rules below let the Customize card grow to fill that column with
    its footer pinned to the bottom.
--}}
<style>
    .fi-theme-left,
    .fi-theme-left > .fi-grid { height: 100%; }
    .fi-theme-left > .fi-grid { grid-template-rows: auto 1fr; }
    .fi-theme-left > .fi-grid > :last-child,
    .fi-theme-left > .fi-grid > :last-child > *,
    #customize,
    #customize > .fi-section,
    #customize .fi-section-content-ctn { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #customize .fi-section-content { flex: 1 1 auto; }
</style>

<div
    x-data="{
        theme: $wire.$entangle('data.theme'),
        base: @js($url),
        param: @js($param),
        get src() {
            return this.theme ? `${this.base}?${this.param}=${encodeURIComponent(this.theme)}` : this.base;
        },
        fit() {
            const top = this.$el.getBoundingClientRect().top + window.scrollY;
            this.$el.style.height = `${Math.max(480, window.innerHeight - top - 24)}px`;
        },
    }"
    x-init="fit(); $nextTick(() => fit())"
    x-on:resize.window="fit()"
    class="fi-theme-preview"
>
    <iframe
        x-bind:src="src"
        title="{{ __('filament.theme_preview') }}"
        loading="lazy"
        class="h-full w-full rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900"
    ></iframe>
</div>
