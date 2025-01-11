<div class="mb-3">
  <label for="{{ $field->slug }}" class="form-label">
    {{ $field->name }}
    @if($field->required === true)
      <span class="text-danger">*</span>
    @endif
    @if(filled($field->description))
      <span class="text-info mx-1"></span>
    @endif
  </label>
  <div class="input-group input-group-sm">
    @if(!$field->read_only)
      <input type="text" name="{{ $field->slug }}" id="{{ $field->slug }}" class="form-control" value="{{ $field->value }}" @if(!empty($pirep) && $pirep->read_only) readonly @endif/>
    @else
      <input type="text" class="form-control-plaintext" value="{{ $field->value }}" readonly/>
    @endif
  </div>
  <p class="text-danger">{{ $errors->first('field_'.$field->slug) }}</p>
</div>
