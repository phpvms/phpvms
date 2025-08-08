@can('view_module')
  <style>
    @media (min-width: 1024px) {
      .lg\:hidden {
        display: none !important;
      }
    }
  </style>

  <ul class="fi-sidebar-nav-groups lg:hidden">
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
