<x-filament-widgets::widget class="fi-filament-info-widget @if($adminLinks->count() === 0) hidden @endif">
  <x-filament::section>
    <div class="flex items-center gap-x-3">
      <div class="flex-1">
        <h1 class="font-bold text-lg">
          Old Admin Modules
        </h1>
      </div>

      <div class="flex flex-col items-end gap-y-1">

        @foreach($adminLinks as $link)
          <x-filament::link
            color="gray"
            href="{{ $link['url'] }}"
            rel="noopener noreferrer"
            target="_blank"
          >
            {{ $link['title'] }}
          </x-filament::link>
        @endforeach
      </div>
    </div>
  </x-filament::section>
</x-filament-widgets::widget>
