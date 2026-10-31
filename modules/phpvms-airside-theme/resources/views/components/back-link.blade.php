@props(['href'])

{{-- "Back to …" link. Prop: href. Slot: the destination text, e.g. "flight manifest" renders "Back to flight manifest". --}}
<x-ts-link :href="$href" :text="'Back to '.trim($slot)" icon="phosphor.arrow-left.light" sm navigate {{ $attributes->class('w-fit') }} />
