@extends('app')
@section('title', trans('profile.simbrief_username'))

@section('content')
  <div class="row">
    <div class="col-md-12">
      <h2>@lang('profile.enter_simbrief_username')</h2>
    </div>
  </div>

  <form id="simbriefUsernameForm" class="row">
    <div class="alert alert-danger d-none mt-2" id="simbriefUsernameError"></div>
    <div class="col-md-12">
      <div class="form-group">
        <label for="simbriefUsername" class="mb-2">@lang('profile.simbrief_username')</label>
        <input type="text" class="form-control" id="simbriefUsername" name="simbriefUsername" required>
      </div>
    </div>
    <div class="col-md-12 mt-2">
      <button type="submit" class="btn btn-primary">@lang('common.submit')</button>
    </div>
  </form>
@endsection

@section('scripts')
  <script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function () {
      document.getElementById('simbriefUsernameForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const username = document.getElementById('simbriefUsername').value;
        const errorBox = document.getElementById('simbriefUsernameError');

        phpvms.request({
          method: 'POST',
          url: '{{ route('api.users.simbrief_username') }}',
          data: {
            simbrief_username: username
          }
        })
          .then(response => {
            if (response.data.success) {
              location.reload();
            } else {
              errorBox.classList.remove('d-none');
              errorBox.textContent = response.data.message || 'An error occurred.';
            }
          })
          .catch(error => {
            console.log(error)
            errorBox.classList.remove('d-none');
            errorBox.textContent = error.response.data.message || error.message || 'Something went wrong.';
          });
      });
    });
  </script>
@endsection
