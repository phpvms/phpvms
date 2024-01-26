@if($user->fields)
  <table class="table table-hover">
    <tr>
      <td colspan="2"><h5>Custom Fields</h5></td>
    </tr>
    {{-- Custom Fields --}}
    @foreach($user->fields as $field)
      <tr>
        <td>{{ $field->field->name }}</td>
        <td>
          @if(in_array($field->name, ['IVAO', 'IVAO ID']))
            <a href='https://www.ivao.aero/Member.aspx?ID={{ $field->value }}' target='_blank'>{{ $field->value }}</a>
          @elseif(in_array($field->name, ['VATSIM', 'VATSIM CID', 'VATSIM ID']))
            <a href='https://stats.vatsim.net/search_id.php?id={{ $field->value }}' target='_blank'>{{ $field->value }}</a>
          @else
            {{ $field->value }}
          @endif
        </td>
      </tr>
    @endforeach
  </table>
@endif
