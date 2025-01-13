@extends('app')
@section('title', 'phpVMS v7 Credits')
@section('content')
  <div class="row row-cols-2">
    <div class="col-sm-5">
      <div class="card">
        <div class="card-header bg-primary text-white">PHPVMS v7</div>
        <div class="card-body p-1">
          <img src="{{ public_asset('/assets/img/logo_blue_bg.svg') }}" width="100%" alt=""/>
          <p class="description">Open-Source Virtual Airline Management</p>
        </div>
        <div class="card-footer text-start p-1">
          <a href="https://docs.phpvms.net" target="_blank" class="btn btn-sm btn-primary">Documents & Guides</a>
          <a href="https://docs.phpvms.net/#license" target="_blank" class="btn btn-sm btn-primary">License</a>
        </div>
      </div>
    </div>
    <div class="col-sm-7">
      @foreach($modules as $module)
        <div class="card mb-2">
          <div class="card-header bg-primary text-white">{{ $module->name }}</div>
          <div class="card-body p-1">
            <p class="description">{{ $module->description }}</p>
            @if($module->version)
              <p class="description">Version: {{ $module->version }}</p>
            @endif
          </div>
          <div class="card-footer text-start p-1">
            @if($module->active)
              <span class="btn btn-success btn-sm disabled" title="Active"><i class="bi bi-check-lg"></i></span>
            @else
              <span class="btn btn-warning btn-sm disabled" title="Not Active"><i class="bi bi-x-lg"></i></span>
            @endif
            <span class="float-end">
              @if($module->attribution)
                <a href="{{ $module->attribution->url }}" target="_blank" class="btn btn-sm btn-outline-danger">{{ $module->attribution->text }}</a>
              @endif
              @if($module->readme_url)
                <a href="{{ $module->readme_url }}" target="_blank" class="btn btn-sm btn-outline-primary">Readme</a>
              @endif
              @if($module->license_url)
                <a href="{{ $module->license_url }}" target="_blank" class="btn btn-sm btn-outline-primary">License</a>
              @endif
            </span>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endsection
