@extends('app')
@section('title', __('common.live_map'))

@section('content')
  {{ Widget::liveMap() }}
@endsection

