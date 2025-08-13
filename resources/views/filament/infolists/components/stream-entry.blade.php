<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
  <div class="fi-in-code">
    <pre class="phiki language-log github-light phiki-themes github-dark-high-contrast" data-language="log"
         style="background-color: #fff;color: #24292e;--phiki-dark-background-color: #0a0c10;--phiki-dark-color: #f0f3f6"><code @if($stream) wire:stream="{{ $stream }}" @endif {{ $getExtraAttributeBag() }}>{{ $getState() }}</code></pre>
  </div>
</x-dynamic-component>
