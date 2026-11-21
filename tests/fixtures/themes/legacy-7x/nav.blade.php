@foreach($moduleSvc->getFrontendLinks(Auth::check()) as $link)
  <a href="{{ $link['url'] }}">{{ $link['title'] }}</a>
@endforeach
@ability('admin', 'admin-access')
  <a href="/admin">Admin</a>
@endability
