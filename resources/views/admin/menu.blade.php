<li>
  <a href="{{ url('/admin') }}"><i class="pe-7s-display1"></i>dashboard</a>
</li>

<li>
  <a data-toggle="collapse" href="#operations_menu" class="menu operations_menu" aria-expanded="true">
    <h5>operations&nbsp;<b class="pe-7s-angle-right"></b></h5>
  </a>

  <div class="collapse" id="operations_menu" aria-expanded="true">
    <ul class="nav">
      @can('ViewAny:Pirep')
        <li><a href="{{ \App\Filament\Resources\Pireps\PirepResource::getUrl() }}"><i class="pe-7s-cloud-upload"></i>pireps
            <span data-toggle="tooltip" title="3 New" class="badge bg-light-blue pull-right">3</span>
          </a>
        </li>
      @endcan

      @can('ViewAny:Flight')
        <li><a href="{{ \App\Filament\Resources\Flights\FlightResource::getUrl() }}"><i class="pe-7s-vector"></i>flights</a>
        </li>
      @endcan

      @can('ViewAny:Subfleet')
        <li><a href="{{ \App\Filament\Resources\Subfleets\SubfleetResource::getUrl() }}"><i class="pe-7s-plane"></i>fleet</a>
        </li>
      @endcan

      @can('ViewAny:Fare')
        <li><a href="{{ \App\Filament\Resources\Fares\FareResource::getUrl() }}"><i class="pe-7s-graph2"></i>fares</a></li>
      @endcan

      @can('View:Finances')
        <li><a href="{{ \App\Filament\Pages\Finances::getUrl() }}"><i class="pe-7s-display1"></i>finances</a></li>
      @endcan

      @can('ViewAny:User')
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
      @can('ViewAny:Airline')
        <li><a href="{{ \App\Filament\Resources\Airlines\AirlineResource::getUrl() }}"><i class="pe-7s-paper-plane"></i>airlines</a>
        </li>
      @endcan

      @can('ViewAny:SimBriefAirframe')
        <li><a href="{{ \App\Filament\Resources\SimBriefAirframes\SimBriefAirframeResource::getUrl() }}"><i class="pe-7s-plane"></i>sb airframes</a></li>
      @endcan

      @can('ViewAny:Airport')
        <li><a href="{{ \App\Filament\Resources\Airports\AirportResource::getUrl() }}"><i
              class="pe-7s-map-marker"></i>airports</a></li>
      @endcan

      @can('ViewAny:Expense')
        <li><a href="{{ \App\Filament\Resources\Expenses\ExpenseResource::getUrl() }}"><i class="pe-7s-cash"></i>expenses</a>
        </li>
      @endcan

      @can('ViewAny:Rank')
        <li><a href="{{ \App\Filament\Resources\Ranks\RankResource::getUrl() }}"><i class="pe-7s-graph1"></i>ranks</a>
        </li>
      @endcan

      @can('ViewAny:Typerating')
        <li><a href="{{ \App\Filament\Resources\Typeratings\TyperatingResource::getUrl() }}"><i class="pe-7s-plane"></i>type
            ratings</a>
        </li>
      @endcan

      @can('ViewAny:Award')
        <li><a href="{!! \App\Filament\Resources\Awards\AwardResource::getUrl() !!}"><i class="pe-7s-diamond"></i>awards</a>
        </li>
      @endcan

      @can('ViewAny:Role')
        <li><a href="{{ \BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::getUrl() }}"><i
              class="pe-7s-network"></i>roles</a></li>
      @endcan

      @can('ViewAny:Page')
        <li><a href="{{ \App\Filament\Resources\Pages\PageResource::getUrl() }}"><i class="pe-7s-note"></i>pages/links</a>
        </li>
      @endcan

      @can('ViewAny:Module')
        <li><a href="{!! \App\Filament\Resources\Modules\ModuleResource::getUrl() !!}"><i class="pe-7s-box2"></i>addons/modules</a>
        </li>
      @endcan

      @can('View:Maintenance')
        <li><a href="{{ \App\Filament\Pages\Maintenance::getUrl() }}"><i class="pe-7s-tools"></i>maintenance</a></li>
      @endcan

      @can('ViewAny:Activity')
        <li><a href="{{ \App\Filament\Resources\ActivityLogs\ActivityLogResource::getUrl() }}"><i class="pe-7s-news-paper"></i>activities</a></li>   
      @endcan

      @can('View:Settings')
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

