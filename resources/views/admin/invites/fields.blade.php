<div class="row">
  <div class="form-group col-sm-4">
    {{ Form::label('email', 'Email:') }}
    {{ Form::email('email', null, ['class' => 'form-control']) }}
    <p class="text-danger">{{ $errors->first('email') }}</p>
    @component('admin.components.info')
      If empty all emails will be allowed to register using the link.
    @endcomponent
  </div>

  <div class="form-group col-sm-4">
    {{ Form::label('usage_limit', 'Usage limit:') }}
    {{ Form::number('usage_limit', null, ['class' => 'form-control']) }}
    <p class="text-danger">{{ $errors->first('usage_limit') }}</p>
    @component('admin.components.info')
      If empty there will be no limit on the number of times the link can be used.
      If an email is provided the limit will be automatically set to 1.
    @endcomponent
  </div>

  <div class="form-group col-sm-4">
    {{ Form::label('expires_at', 'Expiration Date:') }}
    <input type="datetime-local" class="form-control" name="expires_at" id="expires_at" />
    <p class="text-danger">{{ $errors->first('expires_at') }}</p>
    @component('admin.components.info')
      If empty the link will not expire.
    @endcomponent
  </div>
</div>

<div class="row">
  <div class="col-sm-6">
    {{ Form::label('email_link', 'Email Invite Link:') }}
    <label class="checkbox-inline">
      {{ Form::hidden('email_link', 0, false) }}
      {{ Form::checkbox('email_link', 1, null) }}
    </label>
    @component('admin.components.info')
      If checked and an email is provided, the invite will be sent via email.
    @endcomponent
  </div>

  <!-- Submit Field -->
  <div class="form-group col-sm-6">
    <div class="pull-right">
      {{ Form::button('Save', ['type' => 'submit', 'class' => 'btn btn-success']) }}
      <a href="{{ route('admin.userfields.index') }}" class="btn btn-default">Cancel</a>
    </div></div>
</div>
