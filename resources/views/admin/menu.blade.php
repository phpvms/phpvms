<li>
  <a href="{{ url('/admin/dashboard') }}"><i class="pe-7s-display1"></i>dashboard</a>
</li>

<li>
  <a data-toggle="collapse" href="#operations_menu" class="menu operations_menu" aria-expanded="true">
    <h5>operations&nbsp;<b class="pe-7s-angle-right"></b></h5>
  </a>

  <div class="collapse" id="operations_menu" aria-expanded="true">
    <ul class="nav">
      @ability('admin', 'pireps')
      <li><a href="{{ url('/admin/pireps') }}"><i class="pe-7s-cloud-upload"></i>pireps
          <span data-toggle="tooltip" title="3 New" class="badge bg-light-blue pull-right">3</span>
        </a>
      </li>
      @endability

      @ability('admin', 'flights')
      <li><a href="{{ url('/admin/flights') }}"><i class="pe-7s-vector"></i>flights</a></li>
      @endability

      @ability('admin', 'fleet')
      <li><a href="{{ url('/admin/subfleets') }}"><i class="pe-7s-plane"></i>fleet</a></li>
      @endability

      @ability('admin', 'fares')
      <li><a href="{{ url('/admin/fares') }}"><i class="pe-7s-graph2"></i>fares</a></li>
      @endability

      @ability('admin', 'finances')
      <li><a href="{{ url('/admin/finances') }}"><i class="pe-7s-display1"></i>finances</a></li>
      @endability

      @ability('admin', 'users')
      <li><a href="{{ url('/admin/users') }}"><i class="pe-7s-users"></i>users</a></li>
      @endability
    </ul>
  </div>
</li>

<li>
  <a data-toggle="collapse" href="#config_menu" class="menu config_menu" aria-expanded="true">
    <h5>config&nbsp;<b class="pe-7s-angle-right"></b></h5>
  </a>

  <div class="collapse" id="config_menu" aria-expanded="true">
    <ul class="nav">
      @ability('admin', 'airlines')
      <li><a href="{{ url('/admin/airlines') }}"><i class="pe-7s-paper-plane"></i>airlines</a></li>
      @endability

      @ability('admin', 'aircraft', 'fleet')
      <li><a href="{{ url('/admin/airframes') }}"><i class="pe-7s-plane"></i>sb airframes</a></li>
      @endability

      @ability('admin', 'airports')
      <li><a href="{{ url('/admin/airports') }}"><i class="pe-7s-map-marker"></i>airports</a></li>
      @endability

      @ability('admin', 'finances')
      <li><a href="{{ url('/admin/expenses') }}"><i class="pe-7s-cash"></i>expenses</a></li>
      @endability

      @ability('admin', 'ranks')
      <li><a href="{{ url('/admin/ranks') }}"><i class="pe-7s-graph1"></i>ranks</a></li>
      @endability

      @ability('admin', 'typeratings')
      <li><a href="{{ url('/admin/typeratings') }}"><i class="pe-7s-plane"></i>type ratings</a></li>
      @endability

      @ability('admin', 'awards')
      <li><a href="{!! url('/admin/awards') !!}"><i class="pe-7s-diamond"></i>awards</a></li>
      @endability

      @ability('admin', 'users')
      <li><a href="{!! url('/admin/roles') !!}"><i class="pe-7s-network"></i>roles</a></li>
      @endability

      @ability('admin', 'pages')
      <li><a href="{!! url('/admin/pages') !!}"><i class="pe-7s-note"></i>pages/links</a></li>
      @endability

      @ability('admin', 'modules')
      <li><a href="{!! url('/admin/modules') !!}"><i class="pe-7s-box2"></i>addons/modules</a></li>
      @endability

      @ability('admin', 'maintenance')
      <li><a href="{{ url('/admin/maintenance') }}"><i class="pe-7s-tools"></i>maintenance</a></li>
      @endability

      @if(config('activitylog.enabled', true) === true)
        @ability('admin', 'admin-access')
        <li><a href="{{ url('/admin/activities') }}"><i class="pe-7s-news-paper"></i>activities</a></li>
        @endability
      @endif

      @ability('admin', 'settings')
      <li><a href="{{ url('/admin/settings') }}"><i class="pe-7s-config"></i>settings</a></li>
      @endability
    </ul>
  </div>
</li>

<li>
  <a data-toggle="collapse" href="#addons_menu" class="menu addons_menu" aria-expanded="true">
    <h5>addons&nbsp;<b class="pe-7s-angle-right"></b></h5>
  </a>

  <div class="collapse" id="addons_menu" aria-expanded="true">
    <ul class="nav">
      @ability('admin', 'addons')
      @foreach($moduleSvc->getAdminLinks() as &$link)
        <li><a href="{{ url($link['url']) }}"><i class="{{ $link['icon'] }}"></i>{{ $link['title'] }}</a></li>
      @endforeach
      @endability
    </ul>
  </div>
</li>

