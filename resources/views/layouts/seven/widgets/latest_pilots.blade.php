<table class="table table-responsive">
    @foreach ($users as $u)
        <tr>
            <td>
                <span class="title">{{ $u->ident }}</span>
            </td>
            <td>{{ $u->name_private }}</td>
        </tr>
    @endforeach
</table>
