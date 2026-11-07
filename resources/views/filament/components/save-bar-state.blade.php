{{--
    State line of the sticky save bar (see HasSaveBar).

    Dirty count: top-level keys of `$wire.data` whose JSON differs from a
    snapshot taken on init and re-taken on the `save-bar-saved` event (fired
    from HasSaveBar::afterSave). `ignore` lists keys that autosave themselves.
--}}
@php
    $text = [
        'clean' => __('filament.save_bar.clean'),
        'changes' => [trans_choice('filament.save_bar.changes', 1, ['count' => ':n']), trans_choice('filament.save_bar.changes', 2, ['count' => ':n'])],
        'errors' => [trans_choice('filament.save_bar.errors', 1, ['count' => ':n']), trans_choice('filament.save_bar.errors', 2, ['count' => ':n'])],
    ];
@endphp
<span
    class="save-bar-state"
    x-data="{
        text: @js($text),
        ignore: @js($ignore),
        snapshot: {},
        take() {
            const data = JSON.parse(JSON.stringify($wire.data ?? {}))
            this.snapshot = Object.fromEntries(Object.entries(data).map(([k, v]) => [k, JSON.stringify(v ?? null)]))
        },
        get changes() {
            const data = $wire.data ?? {}
            const keys = new Set([...Object.keys(data), ...Object.keys(this.snapshot)])
            let n = 0
            for (const k of keys) {
                if (this.ignore.includes(k)) continue
                if (JSON.stringify(data[k] ?? null) !== (this.snapshot[k] ?? 'null')) n++
            }
            return n
        },
        get errors() { return $wire.$errors.count() },
        plural(forms, n) { return forms[n === 1 ? 0 : 1].replace(':n', n) },
        get label() {
            const n = this.changes
            const e = this.errors
            if (n === 0 && e === 0) return this.text.clean
            const parts = []
            if (n > 0) parts.push(this.plural(this.text.changes, n))
            if (e > 0) parts.push(this.plural(this.text.errors, e))
            return parts.join(' · ')
        },
        init() { this.take() },
    }"
    x-on:save-bar-saved.window="$nextTick(() => take())"
    x-bind:data-dirty="changes > 0 || errors > 0"
>
    <span class="save-bar-dot"></span>
    <span class="save-bar-label" x-text="label"></span>
</span>
