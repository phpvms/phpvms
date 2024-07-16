<div>

</div>

@script
<script>
  Livewire.on('dbsetup-completed', () => {
    $wire.startImport();
  })

  Livewire.on('import-update', ({ data }) => {
    console.log(data);
  })
</script>
@endscript
