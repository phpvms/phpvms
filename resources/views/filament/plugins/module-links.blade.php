@can('view_module')
  <ul class="me-4 hidden items-center gap-x-4 lg:flex">
    <x-filament-panels::topbar.item
      :active="$current_panel->getId() === 'admin'"
      icon="heroicon-o-home"
      :url="url(\Filament\Facades\Filament::getPanel('admin')->getPath())"
    >
      Main v8
    </x-filament-panels::topbar.item>

    @foreach($panels as $panel)
      @php
        if ($panel->getId() === 'admin' || $panel->getId() === 'system') {
          continue;
        }

        $panel_name = ucfirst(str_replace('::admin', '', $panel->getId()));
        $active = str_contains(request()->path(), strtolower($panel_name));
        $icon = 'heroicon-o-puzzle-piece';
      @endphp
      <x-filament-panels::topbar.item
        :active="$active"
        :icon="$icon"
        :url="url($panel->getPath())"
      >
        {{ $panel_name }}
      </x-filament-panels::topbar.item>
    @endforeach


    @foreach($old_links as $link)
      <x-filament-panels::topbar.item
        :active="false"
        :url="$link['url']"
      >
        {{ $link['title'] }}
      </x-filament-panels::topbar.item>
    @endforeach
  </ul>
@endcan
