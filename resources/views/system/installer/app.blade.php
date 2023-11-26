<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title') - installer</title>

  <link rel="shortcut icon" type="image/png" href="{{ public_asset('/assets/img/favicon.png') }}"/>

  <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no'
        name='viewport'/>
  <meta name="base-url" content="{!! url('') !!}">
  <meta name="api-key" content="{!! Auth::check() ? Auth::user()->api_key: '' !!}">
  <meta name="csrf-token" content="{!! csrf_token() !!}">

  <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet"/>
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet"/>

  @vite(['resources/sass/app.scss', 'resources/sass/now-ui/now-ui-kit.scss', 'resources/sass/frontend/styles.scss'])

  <link rel="stylesheet"
        href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/styles/default.min.css">

  <style>
    .table tr:first-child td {
      border-top: 0px;
    }
    @yield('css')
  </style>
</head>

<body class="login-page" style="background: #067ec1;">
<div class="page-header clear-filter">
  <div class="content">
    <div class="container">
      <div class="row">
        <div class="col-md-8 ml-auto mr-auto content-center">
          <div class="p-10" style="padding: 10px 0;">
            <div class="row">
              <div class="col-4">
                <img src="{{ public_asset('/assets/img/logo_blue_bg.svg') }}" width="135px" style="" alt=""/>
              </div>
              <div class="col-8 text-right">
                <h4 class="text-white mb-0 mr-0 ml-0" style="margin-top: 5px;">@yield('title')</h4>
              </div>
            </div>
          </div>
          <div class="card card-login card-plain" style="background: #FFF">
            <div class="card-body">
              @include('system.installer.flash.message')
              @yield('content')
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{--<script src="https://cdn.rawgit.com/google/code-prettify/master/loader/run_prettify.js"></script>--}}

@vite(['resources/js/entrypoint.js', 'resources/js/frontend/app.js'])
<script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js"></script>

<script>
  hljs.configure({languages: ['sh']});

  window.addEventListener("load", function () {

    $(".select2").select2();

    $('pre code').each(function (i, block) {
      hljs.fixMarkup(block);
      hljs.highlightBlock(block);
    });
  });
</script>

@yield('scripts')

</body>
</html>
