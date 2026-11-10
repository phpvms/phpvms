{{--
  A vertical timeline: a time column, a spine with nodes, and a body per entry.
  Slot: default = <x-vms::timeline.entry> elements, one per stack from
  Modules\PhpvmsAirsideTheme\Support\TimelineStack::stack().
--}}
<ol {{ $attributes->class('vms-timeline m-0 list-none p-0') }}>
    {{ $slot }}
</ol>
