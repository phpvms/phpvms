@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
])

{{--
  A labelled form control: TallStackUI's input (or password). The validation error is rendered
  through TallStackUI's error component inside a live region, so assistive tech announces it.
  Extra attributes land on the control.
--}}
@php($id = $attributes->get('id', $name))
<div class="grid gap-1">
    @if ($type === 'password')
        <x-ts-password :label="$label" :name="$name" :id="$id" invalidate {{ $attributes->except('id') }} />
    @else
        <x-ts-input :label="$label" :name="$name" :id="$id" :type="$type" :value="$value" invalidate {{ $attributes->except('id') }} />
    @endif

    @error($name)
        <div role="alert"><x-ts-error :property="$name" /></div>
    @enderror
</div>
