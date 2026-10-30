@props(['name' => null, 'label' => null])

{{--
  Page root (UPage). Props: name (-> class "pv-<name>"), label (aria-label).
  Slot: default = the page body. Pair with <x-airside::page-header> as the first child.
--}}
<div {{ $attributes->class(['grid gap-6', $name ? 'pv-'.$name : null]) }} @if ($label) role="region" aria-label="{{ $label }}" @endif>
    {{ $slot }}
</div>
