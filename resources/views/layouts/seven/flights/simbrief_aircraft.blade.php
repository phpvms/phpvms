@extends('app')
@section('title', 'SimBrief Flight Planning')

@section('content')
  <div class="row">
    <div class="col-md-12">
      <h2>Select Aircraft for Flight</h2>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <select id="aircraftselection" class="form-control select2" onchange="checkacselection()">
        <option value="ZZZZZ">Please Select An Aircraft</option>
          @foreach($aircrafts as $ac)
            <option value="{{ $ac->id }}">[{{ $ac->icao }}] {{ $ac->registration }} @if($ac->registration != $ac->name)'{{ $ac->name }}'@endif</option>
          @endforeach
      </select>
    </div>
    <div class="col-md-12 text-right">
      <a id="generate_link" style="visibility: hidden"
         href="{{ route('frontend.simbrief.generate') }}?flight_id={{ $flight->id }}"
         class="btn btn-primary">Proceed To Flight Planning</a>
    </div>
  </div>
@endsection
@section('scripts')
  <script type="text/javascript">
    // Simple Aircraft Selection With Dropdown Change
    // Also keep Generate button hidden until a valid AC selection
    const $oldlink = document.getElementById("generate_link").href;

    function checkacselection() {
      if (document.getElementById("aircraftselection").value === "ZZZZZ") {
        document.getElementById('generate_link').style.visibility = 'hidden';
      } else {
        document.getElementById('generate_link').style.visibility = 'visible';
      }
      const selectedac = document.getElementById("aircraftselection").value;
      const newlink = "&aircraft_id=".concat(selectedac);

      document.getElementById("generate_link").href = $oldlink.concat(newlink);
    }
  </script>
@endsection
