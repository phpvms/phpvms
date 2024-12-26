<ul class="list-group">
  @foreach($files as $file)
    <li class="list-group-item">
      <a href="{{route('frontend.downloads.download', [$file->id])}}" target="_blank" @if($file->isExternalFile) data-bs-external-redirect="{{ $file->url }}" @endif>
        {{ $file->name }}
      </a>

      {{-- only show description is one is provided --}}
      @if($file->description)
        - {{$file->description}}
      @endif
      @if ($file->download_count > 0)
        <span
          style="margin-left: 20px">{{ $file->download_count.' '.trans_choice('common.download', $file->download_count) }}</span>
      @endif
    </li>
  @endforeach
</ul>
