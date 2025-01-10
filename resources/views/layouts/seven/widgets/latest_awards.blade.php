@if($awards->count() > 0)
  <div class="card border-blue-bottom">
    <div class="card-body">
      <table class="table">
        <tr>
          <td>Ident</td>
          <td>Name</td>
          <td>Award</td>
          <td>Date</td>
        </tr>
        @foreach($awards as $a)
          <tr>
            <td>{{ optional($a->user)->ident }}</td>
            <td>{{ optional($a->user)->name_private }}</td>
            <td>{{ optional($a->award)->name }}</td>
            <td>{{ $a->created_at->format('d.M.Y H:i') }}</td>
          </tr>
        @endforeach
      </table>
    </div>
  </div>
@endif