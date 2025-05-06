<!doctype html>
<html lang="en">
<head>
  <title>@yield('title') - {{ config('app.name') }} admin</title>

  <meta charset="utf-8"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>
  <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport'/>
  <meta name="viewport" content="width=device-width"/>

  {{-- Start of required lines block. DON'T REMOVE THESE LINES! They're required or might break things --}}
  <meta name="base-url" content="{{ url('') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="api-key" content="{{ Auth::check() ? Auth::user()->api_key: '' }}">
  {{-- End the required lines block --}}

  <script src="{{ public_asset('/assets/global/js/jquery.js') }}"></script>

  <link rel="shortcut icon" type="image/png" href="{{ public_asset('/assets/img/favicon.png') }}"/>

  <link href='https://fonts.googleapis.com/css?family=Muli:400,300' rel='stylesheet' type='text/css'/>
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,700,300" rel="stylesheet" type="text/css"/>

  <link rel="stylesheet" href="{{ public_asset('/assets/global/css/vendor.css') }}"/>
  <link rel="stylesheet" href="{{ public_asset('/assets/admin/css/vendor.css') }}"/>
  <link rel="stylesheet" href="{{ public_asset('/assets/admin/css/admin.css') }}"/>

  <style type="text/css">
    @yield('css')
  </style>

  <script>
    const BASE_URL = '{{ url('/') }}';
      @if (Auth::user())
    const PHPVMS_USER_API_KEY = "{{ Auth::user()->api_key }}";
      @else
    const PHPVMS_USER_API_KEY = false;
    @endif
    @yield('scripts_head')
  </script>

</head>
<body>

<div class="wrapper">
  @include('admin.sidebar')

  <div class="main-panel">
    <nav class="navbar navbar-default">
      <div class="container-fluid">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar bar1"></span>
            <span class="icon-bar bar2"></span>
            <span class="icon-bar bar3"></span>
          </button>
          <a class="navbar-brand" href="#">@yield('title')</a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav navbar-right">
            @yield('actions')
          </ul>

        </div>
      </div>
    </nav>


    <div class="content">
      <div class="container-fluid">
        <div class="row">
          {{--@if(\App\Support\Utils::installerEnabled())
            <div class="col-lg-12 alert alert-danger alert-important">
              <p>Remove the modules/Installer folder or set the module to disabled! It's a security risk</p>
            </div>
          @endif--}}

          <div class="col-12">
            @include('admin.flash.message')
            @yield('content')
          </div>
        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="container-fluid">
        <nav class="pull-left">
          <ul>
          </ul>
        </nav>
      </div>
    </footer>
  </div>
</div>
</body>

<script defer src="https://use.fontawesome.com/releases/v5.0.6/js/all.js"></script>
<script defer src="{{ public_asset('/assets/admin/js/vendor.js') }}"></script>
<script defer src="{{ public_asset('/assets/admin/js/app.js') }}"></script>

<script>
  /**
   * Initialize any plugins on the page
   */
  const initPlugins = () => {
    $('.select2').select2({width: 'resolve'});
    $('input').iCheck({
      checkboxClass: 'icheckbox_square-blue',
      radioClass: 'icheckbox_square-blue'
    });

    $('[data-toggle="popover"]').popover();
  };

  $(document).ready(function () {
    initPlugins();

    //let storage = getStorage('phpvms.admin');
    const storage = new phpvms.Storage('phpvms.admin', {
      "menu": [],
    });

    // see what menu items should be open
    const menu = storage.getList('menu');
    for (const id of menu) {
      console.log('found ' + id);
      const elem = $(".collapse#" + id);
      elem.addClass("in").trigger("show.bs.collapse");

      const caret = $("a." + id + " b");
      caret.addClass("pe-7s-angle-down");
      caret.removeClass("pe-7s-angle-right");
    }

    $(".collapse").on("hide.bs.collapse", function () {
      const id = $(this).attr('id');
      const elem = $("a." + id + " b");
      elem.removeClass("pe-7s-angle-down");
      elem.addClass("pe-7s-angle-right");

      // console.log('hiding ' + id);
      storage.removeFromList('menu', id);
      storage.save();
    });

    $(".collapse").on("show.bs.collapse", function () {
      const id = $(this).attr('id');
      const caret = $("a." + id + " b");
      caret.addClass("pe-7s-angle-down");
      caret.removeClass("pe-7s-angle-right");

      // console.log('showing ' + id);
      storage.addToList('menu', id);
      storage.save();
    });
  });
</script>

@yield('scripts')

</html>
