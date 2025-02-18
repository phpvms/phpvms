@can('view_module')
  <ul class="fi-sidebar-nav-groups -mx-2 flex flex-col gap-y-7 lg:hidden">
    <x-filament-panels::sidebar.group
      :active="$group->isActive()"
      :collapsible="$group->isCollapsible()"
      :icon="$group->getIcon()"
      :items="$group->getItems()"
      :label="$group->getLabel()"
      :attributes="\Filament\Support\prepare_inherited_attributes($group->getExtraSidebarAttributeBag())"
    />
  </ul>
@endcan
