<tr>
  <td>
    {{ $field->field->name }}
    @if($field->field->required === true)
      <span class="text-danger">*</span>
    @endif
  </td>
  <td>
    <div class="input-group input-group-sm form-group">
      <input type="text" name="{{ $text->field->slug  }}" id="{{ $text->field->slug }}" class="form-control " value="{{ $field->value }}" />
    </div>
    <p class="text-danger">{{ $errors->first('field_'.$field->slug) }}</p>
  </td>
</tr>
