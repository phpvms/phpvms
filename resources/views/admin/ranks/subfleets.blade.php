<div id="rank_subfleet_wrapper" class="dataTables_wrapper form-inline dt-bootstrap col-lg-12">
  @if(count($rank->subfleets) === 0)
    @include('admin.common.none_added', ['type' => 'subfleets'])
  @endif

  <table class="table table-responsive" id="subfleets-table">
    @if(count($rank->subfleets))
      <thead>
      <th>Airline</th>
      <th>Name</th>
      <th style="text-align: center;">ACARS Pay</th>
      <th style="text-align: center;">Manual Pay</th>
      <th style="text-align: center;">Actions</th>
      </thead>
    @endif
    <tbody>
    @foreach($rank->subfleets as $sf)
      <tr>
        <td>{{ $sf->airline->name }}</td>
        <td>{{ $sf->name }} ({{ $sf->type }})</td>
        <td style="text-align: center;">
          <a href="#" data-pk="{{ $sf->id }}"
             data-name="acars_pay">{{ $sf->pivot->acars_pay }}</a>
        </td>

        <td style="text-align: center;">
          <a href="#" data-pk="{{ $sf->id }}"
             data-name="manual_pay">{{ $sf->pivot->manual_pay }}</a>
        </td>
        <td style="width: 10%; text-align: center;" class="form-inline">
          {{ Form::open(['url' => '/admin/ranks/'.$rank->id.'/subfleets', 'method' => 'delete', 'class' => 'pjax_form']) }}
          {{ Form::hidden('subfleet_id', $sf->id) }}
          <div class='btn-group'>
            {{ Form::button('<i class="fa fa-times"></i>',
                             ['type' => 'submit',
                              'class' => 'btn btn-sm btn-danger btn-icon'])
              }}
          </div>
          {{ Form::close() }}
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
  <hr/>
  <div class="row">
    <div class="col-lg-12">
      <div class="input-group input-group-lg pull-right">
        {{ Form::open(['url' => url('/admin/ranks/'.$rank->id.'/subfleets'),
                        'method' => 'post',
                        'class' => 'pjax_form form-inline'
                        ])
        }}
        <label for="subfleet_ids">Add subfleets:</label>
        {{ Form::select('subfleet_ids[]', $avail_subfleets, null, [
                'multiple' => 'multiple',
                'class' => 'select2 form-control input-lg'])
        }}
        {{ Form::button('<i class="fa fa-plus"></i> Add',
                         ['type' => 'submit',
                          'class' => 'btn btn-success btn-small']) }}
        {{ Form::close() }}
      </div>
    </div>
  </div>
</div>
