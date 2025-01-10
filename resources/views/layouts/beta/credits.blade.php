@extends('app')
@section('title', 'phpVMS v7 Credits')

@section('content')
  <div class="row row-cols-2">
    <div class="col-sm-5">
      <div class="card p-1 mb-2 border-blue-bottom">
        <h4 class="header mt-1">PHPVMS v7</h4>
        <img src="{{ public_asset('/assets/img/logo_blue_bg.svg') }}" width="100%" alt=""/>
        <p class="description">Open-Source Virtual Airline Management</p>
        <div class="footer text-left p-1">
          <a href="https://docs.phpvms.net" target="_blank" class="btn btn-info btn-sm">Documents & Guides</a>
          <a href="https://docs.phpvms.net/#license" target="_blank" class="btn btn-info btn-sm">License</a>
        </div>
      </div>
    </div>
    <div class="col-sm-7">
      @foreach($modules as $module)
        <div class="card p-1 mb-2 border-blue-bottom">
          <h4 class="header mt-1">{{ $module->name }}</h4>
          <p class="description">{{ $module->description }}</p>
          @if($module->version)
            <p class="description">Version: {{ $module->version }}</p>
          @endif
          <div class="footer text-right p-1">
            @if($module->active)
              <span class="btn btn-success btn-sm disabled" title="Active">&checkmark;</span>
            @else
              <span class="btn btn-warning btn-sm disabled" title="Not Active">&cross;</span>
            @endif
            @if($module->attribution)
              <a href="{{ $module->attribution->url }}" target="_blank" class="btn btn-outline-danger btn-sm">{{ $module->attribution->text }}</a>
            @endif
            @if($module->readme_url)
              <a href="{{ $module->readme_url }}" target="_blank" class="btn btn-outline-info btn-sm">Readme</a>
            @endif
            @if($module->license_url)
              <a href="{{ $module->license_url }}" target="_blank" class="btn btn-outline-info btn-sm">License</a>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endsection
