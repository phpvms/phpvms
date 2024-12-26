<table>
  @foreach($users as $u)
    <tr>
      <td style="padding-right: 10px;">
        <span class="title">{{ $u->ident }}</span>
      </td>
      <td>{{ $u->name_private }}</td>
    </tr>
  @endforeach
</table>
