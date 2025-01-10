@extends('app')
@section('title', trans_choice('common.download', 2))

@section('content')
  @include('flash::message')
  <div class="row">
    <div class="row-sm-12">
      <h2>{{ trans_choice('common.download', 2) }}</h2>
    </div>
  </div>
  @if(!$grouped_files || \count($grouped_files) === 0)
    <div class="p-5 mb-4 bg-body-tertiary rounded-3 text-center fs-4">@lang('downloads.none')</div>
  @else
    @foreach($grouped_files as $group => $files)
      <div class="row mb-4">
        <div class="col-12">
          <h4 class="description">{{ $group }}</h4>
        </div>

        <div class="col-12">
          @include('downloads.table', ['files' => $files])
        </div>
      </div>
    @endforeach
  @endif
@endsection

