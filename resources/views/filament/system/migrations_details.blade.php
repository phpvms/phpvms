<div x-data="migrations">
  <pre @migrations-completed.window="update" class="rounded-lg p-4 bg-white dark:bg-gray-800">
    <code x-text="message"></code>
  </pre>
</div>

@script
  <script>
    Alpine.data('migrations', () => ({
      message: 'Processing please wait...',
      update(event) {
        this.message = event.detail.message;
      }
    }));

    Livewire.on('start-migrations', () => {
      $wire.migrate();
    });
  </script>
@endscript
