@props(['name' => null, 'label' => null])

{{--
  Page root. Props: name (adds the class "vms-<name>", a hook for a VA's own CSS), label (aria-label).
  Slot: default = the page body. Pair with <x-vms::page.header> as the first child.
--}}
<div {{ $attributes->class(['grid gap-6', $name ? 'vms-'.$name : null]) }} @if ($label) role="region" aria-label="{{ $label }}" @endif>
    {{ $slot }}
</div>
