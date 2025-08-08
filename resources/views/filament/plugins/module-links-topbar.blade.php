@can('view_module')
  <ul class="fi-topbar-nav-groups">
    <x-filament-panels::topbar.item
      :active="$current_panel->getId() === 'admin'"
      icon="heroicon-o-home"
      :url="url(\Filament\Facades\Filament::getPanel('admin')->getPath())"
    >
      Main v8
    </x-filament-panels::topbar.item>

    @foreach($group->getItems() as $item)
      <x-filament-panels::topbar.item
        :active="str_contains(request()->path(), strtolower($item->getLabel()))"
        :icon="$item->getIcon()"
        :url="$item->getUrl()"
      >
        {{ $item->getLabel() }}
      </x-filament-panels::topbar.item>
    @endforeach
  </ul>
@endcan
