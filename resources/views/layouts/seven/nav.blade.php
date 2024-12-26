<ul class="navbar-nav me-auto mb-2 mb-lg-0 mt-1">
    @if (Auth::check())
        <li class="nav-item">
            <a class="nav-link text-white d-flex gap-1" href="{{ route('frontend.dashboard.index') }}">
                <i class="bi bi-speedometer2"></i>
                <p>@lang('common.dashboard')</p>
            </a>
        </li>
    @endif

    <li class="nav-item">
        <a class="nav-link text-white d-flex gap-1" href="{{ route('frontend.livemap.index') }}">
            <i class="bi bi-globe"></i>
            <p>@lang('common.livemap')</p>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link text-white d-flex gap-1" href="{{ route('frontend.pilots.index') }}">
            <i class="bi bi-people"></i>
            <p>{{ trans_choice('common.pilot', 2) }}</p>
        </a>
    </li>

    {{-- Show the module links that don't require being logged in --}}
    @foreach ($moduleSvc->getFrontendLinks($logged_in = false) as &$link)
        <li class="nav-item">
            <a class="nav-link text-white d-flex gap-1" href="{{ url($link['url']) }}">
                <i class="{{ $link['icon'] }}"></i>
                <p>{{ $link['title'] }}</p>
            </a>
        </li>
    @endforeach

    @foreach ($page_links as $page)
        <li class="nav-item">
            <a class="nav-link text-white d-flex gap-1" href="{{ $page->url }}"
                target="{{ $page->new_window ? '_blank' : '_self' }}">
                <i class="{{ $page['icon'] }}"></i>
                <p>{{ $page['name'] }}</p>
            </a>
        </li>
    @endforeach

    @if (!Auth::check())
        <li class="nav-item">
            <a class="nav-link text-white d-flex gap-1" href="{{ url('/register') }}">
                <i class="bi bi-person-vcard"></i>
                <p>@lang('common.register')</p>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white d-flex gap-1" href="{{ url('/login') }}">
                <i class="bi bi-box-arrow-in-right"></i>
                <p>@lang('common.login')</p>
            </a>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-toggle="dropdown"
                data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                <span
                    class="fi fi-{{ $languages[$locale]['flag-icon'] }}"></span>&nbsp;&nbsp;{{ $languages[$locale]['display'] }}
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                @foreach ($languages as $lang => $language)
                    @if ($lang != $locale)
                        <a class="dropdown-item" href="{{ route('frontend.lang.switch', $lang) }}">
                            <span
                                class="fi fi-{{ $language['flag-icon'] }}"></span>&nbsp;&nbsp;{{ $language['display'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </li>
    @else
        <li class="nav-item">
            <a class="nav-link text-white d-flex gap-1" href="{{ route('frontend.flights.index') }}">
                <i class="fab fa-avianex"></i>
                <p>{{ trans_choice('common.flight', 2) }}</p>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white d-flex gap-1" href="{{ route('frontend.pireps.index') }}">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>{{ trans_choice('common.pirep', 2) }}</p>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white d-flex gap-1" href="{{ route('frontend.downloads.index') }}">
                <i class="fas fa-download"></i>
                <p>{{ trans_choice('common.download', 2) }}</p>
            </a>
        </li>

        {{-- Show the module links for being logged in --}}
        @foreach ($moduleSvc->getFrontendLinks($logged_in = true) as &$link)
            <li class="nav-item">
                <a class="nav-link text-white d-flex gap-1" href="{{ url($link['url']) }}">
                    <i class="{{ $link['icon'] }}"></i>
                    <p>{{ $link['title'] }}</p>
                </a>
            </li>
        @endforeach

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-toggle="dropdown"
                data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                <span
                    class="fi fi-{{ $languages[$locale]['flag-icon'] }}"></span>&nbsp;&nbsp;{{ $languages[$locale]['display'] }}
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                @foreach ($languages as $lang => $language)
                    @if ($lang != $locale)
                        <a class="dropdown-item" href="{{ route('frontend.lang.switch', $lang) }}">
                            <span
                                class="fi fi-{{ $language['flag-icon'] }}"></span>&nbsp;&nbsp;{{ $language['display'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </li>

        <li class="nav-item dropdown ">
            <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdownMenuLink" role="button"
                data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                @if (Auth::user()->avatar == null)
                    <img src="{{ Auth::user()->gravatar(38) }}" style="height: 38px; width: 38px;">
                @else
                    <img src="{{ Auth::user()->avatar->url }}" style="height: 38px; width: 38px;">
                @endif
            </a>
            <div class="dropdown-menu dropdown-menu-left">

                <a class="dropdown-item" href="{{ route('frontend.profile.index') }}">
                    <i class="far fa-user"></i>&nbsp;&nbsp;@lang('common.profile')
                </a>

                @ability('admin', 'admin-access')
                    <a class="dropdown-item" href="{{ url('/admin') }}">
                        <i class="fas fa-circle-notch"></i>&nbsp;&nbsp;@lang('common.administration')
                    </a>
                @endability
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ url('/logout') }}">
                    <i class="fas fa-sign-out-alt"></i>&nbsp;&nbsp;@lang('common.logout')
                </a>
            </div>
        </li>
    @endif

</ul>
