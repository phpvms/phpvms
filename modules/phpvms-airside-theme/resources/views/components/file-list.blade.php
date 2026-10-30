@props(['files'])

{{-- A list of downloadable files (App\Models\File). Props: files. Shows name, description and the download count. --}}
<ul {{ $attributes->class('m-0 grid list-none divide-y divide-line p-0') }} role="list">
    @foreach ($files as $file)
        <li wire:key="file-{{ $file->id }}" class="flex flex-wrap items-baseline justify-between gap-2 py-2 text-[13px]">
            <span class="min-w-0">
                <a href="{{ route('frontend.downloads.download', [$file->id]) }}" target="_blank" rel="noopener"
                   @if ($file->isExternalFile) data-external-redirect="{{ $file->url }}" @endif
                   class="font-medium text-accent hover:underline">{{ $file->name }}</a>
                @if ($file->description)
                    <span class="text-ink-dim">{{ $file->description }}</span>
                @endif
            </span>
            @if ($file->download_count > 0)
                <span class="font-mono text-[11px] text-ink-faint">{{ $file->download_count }} {{ trans_choice('common.download', $file->download_count) }}</span>
            @endif
        </li>
    @endforeach
</ul>
