<tr>
  <td>
    {{ $field->name }}
    @if($field->required === true)
      <span class="text-danger">*</span>
    @endif
    @if(filled($field->description))
      <span class="text-info mx-1"><i class="fas fa-info-circle" title="{{ $field->description }}"></i></span>
    @endif
  </td>
  <td>
    <div class="input-group input-group-sm form-group">
      @if(!$field->read_only)
        <input type="text" name="{{ $field->slug }}" id="{{ $field->slug }}" class="form-control" value="{{ $field->value }}" @if(!empty($pirep) && $pirep->read_only) readonly @endif/>
      @else
        {{ $field->value }}
      @endif
    </div>
    <p class="text-danger">{{ $errors->first('field_'.$field->slug) }}</p>
  </td>
</tr>
