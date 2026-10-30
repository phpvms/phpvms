@props(['href'])

{{-- "← Back to …" link. Prop: href. Slot: the destination text, e.g. "flight manifest" renders "← Back to flight manifest". --}}
<a href="{{ $href }}" wire:navigate {{ $attributes->class('pv-back-link w-fit text-xs font-semibold text-accent hover:underline') }}><span aria-hidden="true">← </span>Back to {{ $slot }}</a>
