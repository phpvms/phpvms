@extends('app')
@section('title', $page->name)

@section('content')
  <div class="row">
    <div class="col-12">
      <h1>{{ $page->name }}</h1>

      {!! $page->body !!}
    </div>
  </div>
@endsection
