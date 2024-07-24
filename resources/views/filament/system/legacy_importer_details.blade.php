{{--}}
<div x-data="legacyImporter">
  <div class="w-full h-5 bg-gray-200 rounded-full dark:bg-gray-700 mt-2" @import-update.window="update">
    <div
      class="text-center text-xs h-5 whitespace-nowrap  p-0.5 rounded-full transition-width duration-600 ease"
      :class="error ? 'bg-danger-600' : 'bg-success-600'" :style="{ width: completed  + '%', backgroundColor: error ? 'rgb({{ \Filament\Support\Colors\Color::Red[500] }})' : 'rgb({{ \Filament\Support\Colors\Color::Green[500] }})' }" x-text="message">

    </div>
  </div>
</div>
{{--}}

<div x-data="legacyImporter" class="relative">
  <div class="absolute inset-0 flex items-center justify-center">
    <span x-text="message" class="text-center text-xs"></span>
  </div>
  <div class="w-full h-5 bg-gray-200 rounded-full dark:bg-gray-700 mt-2" @import-update.window="update">
    <div
      class="h-5 whitespace-nowrap p-0.5 rounded-full transition-width duration-600 ease"
      :class="error ? 'bg-danger-600' : 'bg-success-600'"
      :style="{ width: completed + '%', backgroundColor: error ? 'rgb({{ \Filament\Support\Colors\Color::Red[500] }})' : 'rgb({{ \Filament\Support\Colors\Color::Green[500] }})' }">
    </div>
  </div>
</div>

@script
<script>
  Alpine.data('legacyImporter', () => ({
    completed: 0,
    error: false,
    message: '',
    nextIndex: 0,
    update(event) {
      this.completed = event.detail.completed;
      this.error = event.detail.error;
      this.message = event.detail.message;
      this.nextIndex = event.detail.nextIndex;

     if (this.error === false && this.completed !== 100) {
       $wire.import(this.nextIndex);
     }
    }
  }));

  Livewire.on('dbsetup-completed', () => {
    $wire.import(0);
  });
</script>
@endscript
