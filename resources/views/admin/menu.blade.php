<li>
  <a href="{{ url('/admin') }}"><i class="pe-7s-display1"></i>dashboard</a>
</li>

<li>
  <a data-toggle="collapse" href="#operations_menu" class="menu operations_menu" aria-expanded="true">
    <h5>operations&nbsp;<b class="pe-7s-angle-right"></b></h5>
  </a>

  <div class="collapse" id="operations_menu" aria-expanded="true">
    <ul class="nav">
      @can('view_any_pirep')
      <li><a href="{{ \App\Filament\Resources\PirepResource::getUrl() }}"><i class="pe-7s-cloud-upload"></i>pireps
          <span data-toggle="tooltip" title="3 New" class="badge bg-light-blue pull-right">3</span>
        </a>
      </li>
      @endcan

      @can('view_any_flight')
      <li><a href="{{ \App\Filament\Resources\FlightResource::getUrl() }}"><i class="pe-7s-vector"></i>flights</a></li>
      @endcan

      @can('view_any_subfleet')
      <li><a href="{{ \App\Filament\Resources\SubfleetResource::getUrl() }}"><i class="pe-7s-plane"></i>fleet</a></li>
      @endcan

      @can('view_any_fare')
      <li><a href="{{ \App\Filament\Resources\FareResource::getUrl() }}"><i class="pe-7s-graph2"></i>fares</a></li>
      @endcan

      @can('page_Finances')
      <li><a href="{{ \App\Filament\Pages\Finances::getUrl() }}"><i class="pe-7s-display1"></i>finances</a></li>
      @endcan

      @can('view_any_user')
      <li><a href="{{ \App\Filament\Resources\UserResource::getUrl() }}"><i class="pe-7s-users"></i>users</a></li>
      @endcan
    </ul>
  </div>
</li>

<li>
  <a data-toggle="collapse" href="#config_menu" class="menu config_menu" aria-expanded="true">
    <h5>config&nbsp;<b class="pe-7s-angle-right"></b></h5>
  </a>

  <div class="collapse" id="config_menu" aria-expanded="true">
    <ul class="nav">
      @can('view_any_airline')
      <li><a href="{{ \App\Filament\Resources\AirlineResource::getUrl() }}"><i class="pe-7s-paper-plane"></i>airlines</a></li>
      @endcan

      @can('view_any_airframe')
      <li><a href="{{ url('/admin/airframes') }}"><i class="pe-7s-plane"></i>sb airframes</a></li>
      @endcan

      @can('view_any_airport')
      <li><a href="{{ \App\Filament\Resources\AirportResource::getUrl() }}"><i class="pe-7s-map-marker"></i>airports</a></li>
      @endcan

      @can('view_any_expense')
      <li><a href="{{ \App\Filament\Resources\ExpenseResource::getUrl() }}"><i class="pe-7s-cash"></i>expenses</a></li>
      @endcan

      @can('view_any_rank')
      <li><a href="{{ \App\Filament\Resources\RankResource::getUrl() }}"><i class="pe-7s-graph1"></i>ranks</a></li>
      @endcan

      @can('view_any_typerating')
      <li><a href="{{ \App\Filament\Resources\TyperatingResource::getUrl() }}"><i class="pe-7s-plane"></i>type ratings</a></li>
      @endcan

      @can('view_any_award')
      <li><a href="{!! \App\Filament\Resources\AwardResource::getUrl() !!}"><i class="pe-7s-diamond"></i>awards</a></li>
      @endcan

      @can('view_any_role')
      <li><a href="{!! \BezhanSalleh\FilamentShield\Resources\RoleResource::getUrl() !!}"><i class="pe-7s-network"></i>roles</a></li>
      @endcan

      @can('view_any_page')
      <li><a href="{!! \App\Filament\Resources\PageResource::getUrl() !!}"><i class="pe-7s-note"></i>pages/links</a></li>
      @endcan

      @can('view_any_module')
      <li><a href="{!! \App\Filament\Resources\ModuleResource::getUrl() !!}"><i class="pe-7s-box2"></i>addons/modules</a></li>
      @endcan

      @can('page_Maintenance')
      <li><a href="{{ \App\Filament\Pages\Maintenance::getUrl() }}"><i class="pe-7s-tools"></i>maintenance</a></li>
      @endcan

      <li><a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl() }}"><i class="pe-7s-news-paper"></i>activities</a></li>

      @can('view_logs')
      <li><a href="{{ url('/admin/log-viewer') }}"><i class="pe-7s-note2"></i>logs</a></li>
      @endcan

      @can('page_Settings')
      <li><a href="{{ \App\Filament\Pages\Settings::getUrl() }}"><i class="pe-7s-config"></i>settings</a></li>
      @endcan
    </ul>
  </div>
</li>

<li>
  <a data-toggle="collapse" href="#addons_menu" class="menu addons_menu" aria-expanded="true">
    <h5>addons&nbsp;<b class="pe-7s-angle-right"></b></h5>
  </a>

  <div class="collapse" id="addons_menu" aria-expanded="true">
    <ul class="nav">
      @can('view_any_module')
        @foreach($moduleSvc->getAdminLinks() as &$link)
          <li><a href="{{ url($link['url']) }}"><i class="{{ $link['icon'] }}"></i>{{ $link['title'] }}</a></li>
        @endforeach
      @endcan
    </ul>
  </div>
</li>

