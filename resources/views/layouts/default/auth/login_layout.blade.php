<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8"/>
  <link rel="apple-touch-icon" sizes="76x76" href="/assets/frontend/img/apple-icon.png">
  <link rel="icon" type="image/png" href="/assets/frontend/img/favicon.png">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>
  <title>@yield('title') - {{ config('app.name') }}</title>
  <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no'
        name='viewport'/>
  <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet"/>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css"/>
  @vite(['resources/sass/app.scss', 'resources/sass/now-ui/now-ui-kit.scss', 'resources/sass/frontend/styles.scss'])
  @yield('css')
</head>

<body class="login-page" style="background: #067ec1;">
<!-- Navbar -->

<!-- End Navbar -->
<div class="page-header">
@include('flash::message')

  <div class="container">
    @yield('content')
  </div>
  <footer class="footer">
    <div class="container">
      <div class="copyright">
        &copy;
        <script>
          document.write(new Date().getFullYear())
        </script>
        , powered by
        <a href="http://www.phpvms.net" target="_blank">phpvms</a>. Now-UI by
        <a href="https://www.creative-tim.com" target="_blank">Creative Tim</a>
      </div>
    </div>
  </footer>
</div>
</body>

@vite(['resources/js/entrypoint.js', 'resources/js/frontend/app.js'])

@yield('scripts')

</html>
