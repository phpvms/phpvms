@props(['text' => null])

{{-- Loading state (PvLoadingState). Prop: text (optional, also the accessible name). No slots. --}}
<div {{ $attributes->class('pv-loading-state inline-flex min-w-0 items-center gap-2 text-ink-dim') }} role="status" aria-live="polite" aria-label="{{ $text ?? 'Loading' }}">
    <span class="size-5 shrink-0 animate-spin rounded-full border-2 border-line-strong border-t-accent motion-reduce:animate-none" aria-hidden="true"></span>
    @if ($text)
        <span class="min-w-0">{{ $text }}</span>
    @endif
</div>
