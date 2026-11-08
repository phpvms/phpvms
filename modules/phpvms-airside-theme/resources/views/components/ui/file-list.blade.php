@props(['files'])

{{-- A list of downloadable files (App\Models\File). Props: files. Shows name, description and the download count. --}}
<ul {{ $attributes->class('m-0 grid list-none p-0') }} role="list">
    @foreach ($files as $file)
        <li wire:key="file-{{ $file->id }}" class="flex flex-wrap items-baseline justify-between gap-2 py-2 text-[13px]">
            <span class="min-w-0">
                <a href="{{ route('frontend.downloads.download', [$file->id]) }}" target="_blank" rel="noopener"
                   class="font-medium hover:underline">{{ $file->name }}</a>
                @if ($file->description)
                    <span>{{ $file->description }}</span>
                @endif
            </span>
            @if ($file->download_count > 0)
                <span class="font-mono text-[11px]">{{ $file->download_count }} {{ trans_choice('common.download', $file->download_count) }}</span>
            @endif
        </li>
    @endforeach
</ul>
