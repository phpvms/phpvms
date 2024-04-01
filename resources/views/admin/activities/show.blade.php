@extends('admin.app')
@section('title', 'Activity Details')

@section('content')
  <div class="card border-blue-bottom">
    <div class="content">
      <div class="row">

        <div class="col-xl-12" style="padding: 0 15px;">
          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>&nbsp;Causer Information</h6>
            <div class="form-container-body">
              <div class="row">
                <div class="form-group col-sm-4">
                  <label>Causer Type</label>
                  <p>{{ class_basename($activity->causer_type) }}</p>
                </div>
                <div class="form-group col-sm-4">
                  <label>Causer</label>
                  <p>
                    @if (class_basename($activity->causer_type) === 'User')
                      <a href="{{ route('admin.users.edit', [$activity->causer_id]) }}">
                        {{ $activity->causer_id .' | '. $activity->causer->name_private }}
                      </a>
                    @else
                      {{ $activity->causer_id.' | '. class_basename($activity->causer_type) }}
                    @endif
                  </p>
                </div>
                <div class="form-group col-sm-4">
                  <label>Caused At</label>
                  <p>{{ $activity->created_at->diffForHumans() . ' | ' .$activity->created_at->format('d.M') }}</p>

                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-12" style="padding: 0 15px;">
          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>&nbsp;Subject Information</h6>
            <div class="form-container-body">
              <div class="row">
                <div class="form-group col-sm-3">
                  <label>Subject Type</label>
                  <p>{{ class_basename($activity->subject_type) }}</p>
                </div>
                <div class="form-group col-sm-3">
                  <label>Subject Id</label>
                  <p>
                    {{ $activity->subject_id }}
                  </p>
                </div>
                <div class="form-group col-sm-3">
                  <label>Subject Name</label>
                  <p>
                    {{ $activity->subject->name ?? 'N/A' }}
                  </p>
                </div>
                <div class="form-group col-sm-3">
                  <label>Event Type</label>
                  <p>{{ $activity->event }}</p>
                </div>
              </div>

              @if (isset($activity->changes['attributes']) && is_array($activity->changes['attributes']))
                <div class="table-responsive table-full-width">
                  <table class="table table-hover" id="flights-table">
                    <thead>
                    <th>Field</th>
                    <th>New Value</th>
                    <th>Old Value</th>
                    </thead>
                    <tbody>
                    {{-- Check if 'attributes' key exists --}}
                    @foreach($activity->changes['attributes'] as $field => $newValue)
                       @if(!is_array($newValue))
                        <tr>
                          <td>{{ $field }}</td>
                          <td>{{ $newValue }}</td>
                          {{-- Check if 'old' key exists --}}
                          <td>{{ $activity->changes['old'][$field] ?? 'N/A' }}</td>
                        </tr>
                       @else
                         @foreach($newValue as $subField => $newSubFieldValue)
                           <td>{{ $field.'.'.$subField}}</td>
                           <td>
                             {{ $newSubFieldValue }}
                           </td>
                           {{-- Check if 'old' key exists --}}
                           <td>{{ $activity->changes['old'][$field][$subField] ?? 'N/A' }}</td>
                         @endforeach
                       @endif
                    @endforeach
                    </tbody>
                  </table>
                </div>
              @endif

            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
@endsection
