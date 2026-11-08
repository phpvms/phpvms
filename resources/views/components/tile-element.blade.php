{{--
    An element tile: `<tag attr="value">` plus the ES module that defines it.
    Attribute values are cast to string and escaped by `{{ }}`. A boolean is written
    by presence (true: `attr`, false: omitted), as HTML boolean attributes are.
    `@once` keys on the module, so tiles sharing a module load it once per page.
--}}
@once('tile-module:'.$module)
<script type="module" src="{{ $module }}"></script>
@endonce
<{{ $tag }}@foreach ($elementAttributes as $name => $value){!! $value === true ? ' '.e($name) : ($value === false ? '' : ' '.e($name).'="'.e((string) $value).'"') !!}@endforeach></{{ $tag }}>
