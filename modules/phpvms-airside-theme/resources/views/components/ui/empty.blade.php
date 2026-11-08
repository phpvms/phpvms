@props(['icon' => null, 'title' => null, 'description' => null])

{{--
  Empty state. Props: icon (Phosphor, tabler or heroicon name, optional), title, description.
  Slots: actions (buttons), default (extra content).
--}}
@php($iconName = \Modules\PhpvmsAirsideTheme\Support\NavigationIcon::tallstack($icon))
<div {{ $attributes->class('grid justify-items-center gap-2 rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center dark:border-dark-600') }}>
    @if ($iconName)
        <x-ts-icon :name="$iconName" 2xl gray />
    @endif
    @if ($title)
        <p class="text-sm font-semibold">{{ $title }}</p>
    @endif
    @if ($description)
        <p class="max-w-md text-sm text-dark-500 dark:text-dark-400">{{ $description }}</p>
    @endif
    {{ $slot }}
    @isset($actions)
        <div class="mt-2 flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
