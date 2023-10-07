<div id="airports_table_wrapper">
  <table class="table table-hover table-responsive" id="airports-table">
    <thead>
    <th>@sortablelink('icao', 'ICAO')</th>
    <th>@sortablelink('iata', 'IATA')</th>
    <th>@sortablelink('name', 'Name')</th>
    <th>@sortablelink('location', 'Location')</th>
    <th>@sortablelink('region', 'Region')</th>
    <th>@sortablelink('country', 'Country')</th>
    <th>@sortablelink('elevation', 'Elevation')</th>    
    <th style="text-align: center;">@sortablelink('hub', 'Hub')</th>
    <th style="text-align: center;">@sortablelink('notes', 'Notes')</th>
    <th style="text-align: center;">GH Cost</th>
    <th style="text-align: center;">JetA</th>
    <th style="text-align: center;">100LL</th>
    <th style="text-align: center;">MOGAS</th>
    <th></th>
    </thead>
    <tbody>
    @foreach($airports as $airport)
      <tr>
        <td><a href="{{ route('admin.airports.edit', [$airport->id]) }}">{{ $airport->icao }}</a></td>
        <td><a href="{{ route('admin.airports.edit', [$airport->id]) }}">{{ $airport->iata }}</a></td>
        <td>{{ $airport->name }}</td>
        <td>{{ $airport->location }}</td>
        <td>{{ $airport->region }}</td>
        <td>{{ $airport->country }}</td>
        <td>{{ $airport->elevation }}</td>
        <td style="text-align: center;">
          @if($airport->hub === true)
            <span class="label label-success">Hub</span>
          @endif
        </td>
        <td style="text-align: center;">
          @if(filled($airport->notes))
            <span class="label label-info" title="{{ $airport->notes }}">Notes</span>
          @endif
        </td>
        <td style="text-align: center;">
          {{ $airport->ground_handling_cost }}
        </td>
        <td style="text-align: center;">
          <a class="inline" href="#" data-pk="{{ $airport->id }}"
             data-name="fuel_jeta_cost">{{ $airport->fuel_jeta_cost }}</a>
        </td>
        <td style="text-align: center;">
          <a class="inline" href="#" data-pk="{{ $airport->id }}"
             data-name="fuel_100ll_cost">{{ $airport->fuel_100ll_cost }}</a>
        </td>
        <td style="text-align: center;">
          <a class="inline" href="#" data-pk="{{ $airport->id }}"
             data-name="fuel_mogas_cost">{{ $airport->fuel_mogas_cost }}</a>
        </td>
        <td style="text-align: right;">
          {{ Form::open(['route' => ['admin.airports.destroy', $airport->id], 'method' => 'delete']) }}
          <a href="{{ route('admin.airports.edit', [$airport->id]) }}" class='btn btn-sm btn-success btn-icon'><i
              class="fas fa-pencil-alt"></i></a>
          {{ Form::button('<i class="fa fa-times"></i>', ['type' => 'submit', 'class' => 'btn btn-sm btn-danger btn-icon', 'onclick' => "return confirm('Are you sure?')"]) }}
          {{ Form::close() }}
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
