@props(['text' => null])

{{-- Loading state: TallStackUI's spinner inside a live region. Prop: text (optional, also the accessible name). --}}
<div {{ $attributes->class('inline-flex min-w-0 items-center') }} role="status" aria-live="polite" aria-label="{{ $text ?? 'Loading' }}">
    <x-ts-spinner sm :text="$text" />
</div>
