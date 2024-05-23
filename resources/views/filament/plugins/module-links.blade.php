<ul class="me-4 hidden items-center gap-x-4 lg:flex">
  @php
    $show_main = false;
  @endphp
  @foreach($panels as $panel)
    @php
      if ($panel->getId() === 'admin') {
        continue;
      }

      $panel_name = ucfirst(str_replace('::admin', '', $panel->getId()));
      $active = str_contains(url()->current(), $panel_name);
      $icon = 'heroicon-o-puzzle-piece';

      if ($active) {
        $show_main = true;
      }
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
