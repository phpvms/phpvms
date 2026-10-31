{{--
    Live preview of the site under the theme picked in the select.

    The frame loads the pilot dashboard as the signed-in admin, with
    `?preview_theme=<name>` which SetActiveTheme honours for one request when
    the user has admin access. Nothing is saved until the form is submitted;
    changing the select only swaps the frame's URL. Navigating inside the
    frame drops the parameter, so the frame falls back to the saved theme.

    The frame also previews unsaved CSS live: the editor's `data.css` state is
    written into the frame's `style[data-theme-css]` element (replacing its
    content, so a saved override never keeps stale properties). Save persists it.

    The light/dark control drives the frame's own TallStackUI `tallstackui_darkTheme`
    component on its <html> (`x-bind:class="{ dark: darkTheme }"`). It sets only
    `darkTheme`, the derived flag the class binds to. That component persists to
    localStorage['dark-theme'] solely from a `$watch('mode')`, and the frame shares
    this origin, so writing `mode` would overwrite the admin's saved pilot-site
    preference. Writing `darkTheme` leaves it alone, and the binding keeps the class
    in step instead of overwriting a hand-toggled one. Without Alpine in the frame
    it falls back to toggling the class. Re-applied on frame load and after the
    frame's `livewire:navigated`.

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
    #customize .fi-section-content { flex: 1 1 auto; grid-template-rows: minmax(0, 1fr); }
    #customize .fi-section-footer { margin-top: auto; }
    #customize .fi-section-content > .fi-grid-col,
    #customize .fi-section-content > .fi-grid-col > .fi-sc-component { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #customize .cm-editor { max-height: 60vh; }
    #customize .cm-scroller { overflow: auto; }
</style>

{{-- wire:ignore: Livewire morphs would drop the inline height set by fit() and collapse the iframe. --}}
<div
    wire:ignore
    x-data="{
        mode: 'light',
        userChose: false,
        theme: $wire.$entangle('data.theme'),
        css: $wire.$entangle('data.css'),
        base: @js($url),
        param: @js($param),
        get src() {
            return this.theme ? `${this.base}?${this.param}=${encodeURIComponent(this.theme)}` : this.base;
        },
        applyCss() {
            try {
                const doc = this.$refs.frame?.contentDocument;
                if (!doc || !doc.head) {
                    return;
                }
                let style = doc.head.querySelector('style[data-theme-css]');
                if (!style) {
                    style = doc.createElement('style');
                    style.setAttribute('data-theme-css', '');
                    doc.head.appendChild(style);
                }
                style.textContent = this.css ?? '';
            } catch (e) {
                // cross-origin or not-yet-loaded document
            }
        },
        applyMode() {
            try {
                const win = this.$refs.frame?.contentWindow;
                const root = win?.document?.documentElement;
                if (!root) {
                    return;
                }
                const dark = this.mode === 'dark';
                const state = win.Alpine?.$data(root);
                if (state && 'darkTheme' in state) {
                    state.darkTheme = dark;
                } else {
                    root.classList.toggle('dark', dark);
                }
            } catch (e) {
                // cross-origin or not-yet-loaded document
            }
        },
        syncMode() {
            if (this.userChose) {
                this.applyMode();
                return;
            }
            try {
                const win = this.$refs.frame?.contentWindow;
                const root = win?.document?.documentElement;
                if (!root) {
                    return;
                }
                const state = win.Alpine?.$data(root);
                const dark = state && 'darkTheme' in state ? !!state.darkTheme : root.classList.contains('dark');
                this.mode = dark ? 'dark' : 'light';
            } catch (e) {
                // cross-origin or not-yet-loaded document
            }
        },
        setMode(mode) {
            this.userChose = true;
            this.mode = mode;
            this.applyMode();
        },
        onFrameLoad() {
            this.applyCss();
            this.syncMode();
            try {
                const doc = this.$refs.frame?.contentWindow?.document;
                if (!doc) {
                    return;
                }
                // Until the user picks a mode, the toggle mirrors the frame's own state. The frame's
                // darkTheme initialises from localStorage during its own Alpine init, which can run
                // after this load handler, so sync again once that has happened and after a short delay.
                if (doc.documentElement._x_dataStack) {
                    setTimeout(() => this.syncMode(), 0);
                } else {
                    doc.addEventListener('alpine:initialized', () => this.syncMode(), { once: true });
                }
                setTimeout(() => this.syncMode(), 300);
                if (doc.__previewModeBound) {
                    return;
                }
                doc.__previewModeBound = true;
                doc.addEventListener('livewire:navigated', () => setTimeout(() => {
                    if (this.userChose) {
                        this.applyMode();
                    }
                }, 0));
            } catch (e) {
                // cross-origin or not-yet-loaded document
            }
        },
        fit() {
            const top = this.$el.getBoundingClientRect().top;
            this.$el.style.height = `${Math.max(480, window.innerHeight - top - 24)}px`;
        },
    }"
    x-init="fit(); $nextTick(() => fit()); $watch('css', Alpine.debounce(() => applyCss(), 150))"
    x-on:resize.window="fit()"
    x-on:scroll.window.passive="fit()"
    class="fi-theme-preview"
    style="display: flex; flex-direction: column; gap: 0.5rem;"
>
    <div class="flex justify-end">
        <div class="inline-flex rounded-lg border border-gray-200 p-0.5 text-sm dark:border-white/10" role="group" aria-label="{{ __('filament.theme_preview') }}">
            <button
                type="button"
                x-on:click="setMode('light')"
                x-bind:aria-pressed="mode === 'light'"
                x-bind:class="mode === 'light' ? 'bg-gray-100 text-gray-950 dark:bg-white/10 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
                class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium"
            >
                @svg('phosphor-sun-light', ['class' => 'h-4 w-4'])
                {{ __('filament.theme_preview_light') }}
            </button>
            <button
                type="button"
                x-on:click="setMode('dark')"
                x-bind:aria-pressed="mode === 'dark'"
                x-bind:class="mode === 'dark' ? 'bg-gray-100 text-gray-950 dark:bg-white/10 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
                class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium"
            >
                @svg('phosphor-moon-light', ['class' => 'h-4 w-4'])
                {{ __('filament.theme_preview_dark') }}
            </button>
        </div>
    </div>
    <iframe
        x-ref="frame"
        x-bind:src="src"
        x-on:load="onFrameLoad()"
        title="{{ __('filament.theme_preview') }}"
        loading="lazy"
        style="flex: 1 1 auto; min-height: 0;"
        class="w-full rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900"
    ></iframe>
</div>
