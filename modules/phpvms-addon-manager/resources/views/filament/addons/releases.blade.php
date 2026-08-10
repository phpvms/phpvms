{{--
    Release history from the registry. Version, channel, size and date only — no
    prose, because the registry does not reliably carry release notes and an
    invented summary is worse than none.
--}}
@php
    $sel = $this->selected();
@endphp

<div class="table-wrap">
    <table class="tbl">
        <thead>
            <tr>
                <th scope="col">{{ __('addon-manager::addons.version') }}</th>
                <th scope="col">{{ __('addon-manager::addons.channel') }}</th>
                <th scope="col" class="r">{{ __('addon-manager::addons.label_size') }}</th>
                <th scope="col" class="r">{{ __('addon-manager::addons.released') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->releases() as $release)
                <tr>
                    <td>
                        <span class="id tbl__primary">{{ $release['version'] ?? '—' }}</span>
                        @if (($release['version'] ?? null) === $sel['installed_version'])
                            <span class="fi-text-tertiary">{{ __('addon-manager::addons.installed') }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="chip chip--plain chip--mute">{{ $release['channel'] ?? 'stable' }}</span>
                    </td>
                    <td class="r">
                        <span class="id">
                            {{ isset($release['size']) ? \Illuminate\Support\Number::fileSize((int) $release['size'], 1) : '—' }}
                        </span>
                    </td>
                    <td class="r">
                        <span class="id">
                            {{ isset($release['released_at']) ? \Illuminate\Support\Carbon::parse($release['released_at'])->isoFormat('D MMM') : '—' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
