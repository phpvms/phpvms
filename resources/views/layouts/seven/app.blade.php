<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no'
        name='viewport' />

    <title>@yield('title') - {{ config('app.name') }}</title>
    <script>
        // Check for saved user preference, if any, on initial load
        (function() {
          if (localStorage.getItem('theme') === 'dark' || ((!localStorage.getItem('theme') || localStorage.getItem('theme') === 'auto') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-bs-theme', "dark")
            }
        })();
    </script>

    {{-- Start of required lines block. DON'T REMOVE THESE LINES! They're required or might break things --}}
    <meta name="base-url" content="{!! url('') !!}">
    <meta name="api-key" content="{!! Auth::check() ? Auth::user()->api_key : '' !!}">
    <meta name="csrf-token" content="{!! csrf_token() !!}">
    {{-- End the required lines block --}}

    <link rel="shortcut icon" type="image/png" href="{{ public_asset('/assets/img/favicon.png') }}" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css" />
    <link href="{{public_asset('/assets/vendor/tomselect/tom-select.bootstrap5.css')}}" rel="stylesheet">

    {{-- Start of the required files in the head block --}}
    {{-- <link href="{{ public_mix('/assets/global/css/vendor.css') }}" rel="stylesheet" /> --}}
    @yield('css')
    @yield('scripts_head')
    {{-- End of the required stuff in the head block --}}

    <style>
        .bg-primary {
            background-color: #067ec1 !important;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="{{ url('/') }}">
                <img src="{{ public_asset('/assets/img/logo_blue_bg.svg') }}" width="135px" alt="phpvms Logo" />
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                @include('nav')
            </div>
        </div>
    </nav>
    <!-- /Navbar -->
    <div id="top_anchor" class="clearfix" style="height: 25px;"></div>
    <div class="wrapper">
        <div class="clear"></div>
        <div class="container-fluid" style="width: 85%!important;">

            {{-- These should go where you want your content to show up --}}
            @include('flash.message')
            @yield('content')
            {{-- End the above block --}}

        </div>
        <footer class="d-flex flex-wrap justify-content-between align-items-center py-3 mt-4 border-top" style="padding: 0 7.5%;">
            <div class="col-md-4 d-flex align-items-center">
                <span class="mb-3 mb-md-0 text-body-secondary">Copyright {{ date('Y') }} {{ config('app.name') }}</span>
            </div>
            <div class="col-md-4 d-flex align-items-center justify-content-end">
                <span class="mb-3 mb-md-0 text-body-secondary text-end">Powered by <a href="https://www.phpvms.net" target="_blank">phpVMS</a></span>
            </div>
        </footer>
    </div>

    {{-- External Redirects Modal --}}
    @include('external_redirect_modal')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/js/tom-select.complete.min.js"></script>

    {{-- Start of the required tags block. Don't remove these or things will break!! --}}
    <script src="{{ public_mix('/assets/global/js/vendor.js') }}"></script>
    <script src="{{ public_mix('/assets/frontend/js/vendor.js') }}"></script>
    <script src="{{ public_mix('/assets/frontend/js/app.js') }}"></script>
    @yield('scripts')

    {{-- This is the color theme switcher --}}
    @include('scripts.bs_theme')

    {{--
It's probably safe to keep this to ensure you're in compliance
with the EU Cookie Law https://privacypolicies.com/blog/eu-cookie-law
--}}
    <script>
        window.addEventListener("load", function() {
            window.cookieconsent.initialise({
                palette: {
                    popup: {
                        background: "#edeff5",
                        text: "#838391"
                    },
                    button: {
                        "background": "#067ec1"
                    }
                },
                position: "top",
            })
        });
    </script>
    {{-- End the required tags block --}}

    <script>
        $(document).ready(function() {
            $("select.select2").select2({
                width: 'resolve'
            });
        });
    </script>

    {{--
Google Analytics tracking code. Only active if an ID has been entered
You can modify to any tracking code and re-use that settings field, or
just remove it completely. Only added as a convenience factor
--}}
    @php
        $gtag = setting('general.google_analytics_id');
    @endphp
    @if ($gtag)
        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gtag }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', new Date());

            gtag('config', '{{ $gtag }}');
        </script>
    @endif
    {{-- End of the Google Analytics code --}}

</body>

</html>
