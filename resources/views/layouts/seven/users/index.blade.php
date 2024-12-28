@extends('app')
@section('title', trans_choice('common.pilot', 2))

@section('content')
    <div class="row">
        <div class="col-md-12">
            <h2>{{ trans_choice('common.pilot', 2) }}</h2>
            <div class="card">
                <div class="card-body p-4">
                    @include('users.table')
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12 text-center">
            {{ $users->withQueryString()->links('pagination.bootstrap-5') }}
        </div>
    </div>
@endsection
