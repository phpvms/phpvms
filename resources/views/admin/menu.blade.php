<li>
  <a href="{{ url('/admin') }}"><i class="pe-7s-display1"></i>dashboard</a>
</li>

<li>
  <a data-toggle="collapse" href="#operations_menu" class="menu operations_menu" aria-expanded="true">
    <h5>operations&nbsp;<b class="pe-7s-angle-right"></b></h5>
  </a>

  <div class="collapse" id="operations_menu" aria-expanded="true">
    <ul class="nav">
      @can('view-any:pirep')
        <li><a href="{{ \App\Filament\Resources\Pireps\PirepResource::getUrl() }}"><i class="pe-7s-cloud-upload"></i>pireps
            <span data-toggle="tooltip" title="3 New" class="badge bg-light-blue pull-right">3</span>
          </a>
        </li>
      @endcan

      @can('view-any:flight')
        <li><a href="{{ \App\Filament\Resources\Flights\FlightResource::getUrl() }}"><i class="pe-7s-vector"></i>flights</a>
        </li>
      @endcan

      @can('view-any:subfleet')
        <li><a href="{{ \App\Filament\Resources\Subfleets\SubfleetResource::getUrl() }}"><i class="pe-7s-plane"></i>fleet</a>
        </li>
      @endcan

      @can('view-any:fare')
        <li><a href="{{ \App\Filament\Resources\Fares\FareResource::getUrl() }}"><i class="pe-7s-graph2"></i>fares</a></li>
      @endcan

      @can('view:finances')
        <li><a href="{{ \App\Filament\Pages\Finances::getUrl() }}"><i class="pe-7s-display1"></i>finances</a></li>
      @endcan

      @can('view-any:user')
        <li><a href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}"><i class="pe-7s-users"></i>users</a>
        </li>
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
      @can('view-any:airline')
        <li><a href="{{ \App\Filament\Resources\Airlines\AirlineResource::getUrl() }}"><i class="pe-7s-paper-plane"></i>airlines</a>
        </li>
      @endcan

      @can('view-any:sim-brief-airframe')
        <li><a href="{{ \App\Filament\Resources\SimBriefAirframes\SimBriefAirframeResource::getUrl() }}"><i class="pe-7s-plane"></i>sb airframes</a></li>
      @endcan

      @can('view-any:airport')
        <li><a href="{{ \App\Filament\Resources\Airports\AirportResource::getUrl() }}"><i
              class="pe-7s-map-marker"></i>airports</a></li>
      @endcan

      @can('view-any:expense')
        <li><a href="{{ \App\Filament\Resources\Expenses\ExpenseResource::getUrl() }}"><i class="pe-7s-cash"></i>expenses</a>
        </li>
      @endcan

      @can('view-any:rank')
        <li><a href="{{ \App\Filament\Resources\Ranks\RankResource::getUrl() }}"><i class="pe-7s-graph1"></i>ranks</a>
        </li>
      @endcan

      @can('view-any:typerating')
        <li><a href="{{ \App\Filament\Resources\Typeratings\TyperatingResource::getUrl() }}"><i class="pe-7s-plane"></i>type
            ratings</a>
        </li>
      @endcan

      @can('view-any:award')
        <li><a href="{!! \App\Filament\Resources\Awards\AwardResource::getUrl() !!}"><i class="pe-7s-diamond"></i>awards</a>
        </li>
      @endcan

      @can('view-any:role')
        <li><a href="{{ \BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::getUrl() }}"><i
              class="pe-7s-network"></i>roles</a></li>
      @endcan

      @can('view-any:page')
        <li><a href="{{ \App\Filament\Resources\Pages\PageResource::getUrl() }}"><i class="pe-7s-note"></i>pages/links</a>
        </li>
      @endcan

      @can('view:modules')
        <li><a href="{!! \App\Filament\Resources\Modules\ModuleResource::getUrl() !!}"><i class="pe-7s-box2"></i>addons/modules</a>
        </li>
      @endcan

      @can('view:maintenance')
        <li><a href="{{ \App\Filament\Pages\Maintenance::getUrl() }}"><i class="pe-7s-tools"></i>maintenance</a></li>
      @endcan

      @can('view-any:activity')
        <li><a href="{{ \App\Filament\Resources\ActivityLogs\ActivityLogResource::getUrl() }}"><i class="pe-7s-news-paper"></i>activities</a></li>
      @endcan

      @can('view:settings')
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
      @can('view:modules')
        @foreach($moduleSvc->getAdminLinks() as &$link)
          <li><a href="{{ url($link['url']) }}"><i class="{{ $link['icon'] }}"></i>{{ $link['title'] }}</a></li>
        @endforeach
      @endcan
    </ul>
  </div>
</li>

