@extends('admin.app')
@section('title', 'Import Flights')
@section('content')
<div class="card border-blue-bottom">
  <div class="content">
    <div class="row">
    <form method="post" action="{{ route('admin.flights.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="form-group col-12">
        <label for="csv_file">Choose a CSV file to import</label>
        <input type="file" name="csv_file" accept=".csv">
        <p class="text-danger">{{ $errors->first('csv_file') }}</p>
        <div class="checkbox">
          <label class="checkbox-inline">
            <input type="checkbox" name="delete" value="all" id="deleteAll"> Delete <b>ALL</b> Flights
          </label>
        </div>
        <div class="checkbox">
          <label class="checkbox-inline">
            <input type="checkbox" name="delete" value="core" id="deleteCore"> Delete Only Core (Non-Module Owned/Controlled) Flights
          </label>
        </div>
      </div>
      <div class="form-group col-md-12">
        <div class="text-right">
          <script>
            
            document.querySelector('form').addEventListener('submit', function(event) {
              if (document.querySelector('input[name="delete"][value="all"]').checked) {
                if (!confirm('Are you sure you want to delete all flights? This action is irreversible and can cause problems.')) {
                  event.preventDefault();
                }
              }
            });
          </script>
          <button type="submit" class="btn btn-success">Start Import</button>
        </div>
      </div>
    </form>

      <div class="form-group col-md-12">
        @if($logs['success'])
          <h4>Logs</h4>
          @foreach($logs['success'] as $line)
            <p>{{ $line }}</p>
          @endforeach
        @endif

        @if($logs['errors'])
          <h4>Errors</h4>
          @foreach($logs['errors'] as $line)
            <p>{{ $line }}</p>
          @endforeach
        @endif
      </div>
    </div>
  </div>
</div>

@endsection
