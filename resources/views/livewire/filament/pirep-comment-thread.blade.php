@php
    /** @var \App\Models\Pirep $record */
    /** @var bool $canComment */
    $record = $this->record;

    $pilotName = $record->user?->name ?? '—';
    $pilotInitials = collect(explode(' ', trim((string) $pilotName)))
        ->filter()
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    if ($pilotInitials === '') {
        $pilotInitials = '?';
    }
    $pilotHue = abs(crc32((string) ($record->user_id ?? $record->id))) % 360;

    $hasNote = filled($record->notes);
    $comments = $record->comments ?? collect();
@endphp

<div class="fi-pirep-detail-v2-card">
    <div class="fi-pirep-detail-v2-card-head">
        <h3>{{ __('common.notes_and_comments') }}</h3>
    </div>

    <div class="fi-pirep-detail-v2-card-body">
        @if (! $hasNote && $comments->isEmpty())
            <div class="fi-pirep-detail-v2-chat-empty">{{ __('common.no_notes_or_comments') }}</div>
        @else
            <ul class="fi-pirep-detail-v2-chat" role="list">
                {{-- Pilot note pinned on top, styled like the first message in the thread. --}}
                @if ($hasNote)
                    <li class="fi-pirep-detail-v2-chat-row pilot-note">
                        <div class="avatar"
                             style="background: linear-gradient(135deg, hsl({{ $pilotHue }}, 80%, 55%), hsl({{ ($pilotHue + 20) % 360 }}, 80%, 45%));">
                            {{ $pilotInitials }}
                        </div>
                        <div class="bubble">
                            <div class="message">{!! $record->notes !!}</div>
                            <div class="meta">
                                <span class="who">{{ $pilotName }}</span>
                                <span class="dot">·</span>
                                <span class="tag">{{ __('common.pilot_note') }}</span>
                                @if ($record->submitted_at)
                                    <span class="dot">·</span>
                                    <span class="time" title="{{ $record->submitted_at->format('d-m-Y H:i') }}">{{ $record->submitted_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endif

                @foreach ($comments as $comment)
                    @php
                        $author = $comment->user?->name ?? '—';
                        $authorInitials = collect(explode(' ', trim((string) $author)))
                            ->filter()
                            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->take(2)
                            ->implode('');
                        if ($authorInitials === '') {
                            $authorInitials = '?';
                        }
                        $authorHue = abs(crc32((string) ($comment->user_id ?? $comment->id))) % 360;
                    @endphp

                    <li class="fi-pirep-detail-v2-chat-row">
                        <div class="avatar"
                             style="background: linear-gradient(135deg, hsl({{ $authorHue }}, 80%, 55%), hsl({{ ($authorHue + 20) % 360 }}, 80%, 45%));">
                            {{ $authorInitials }}
                        </div>
                        <div class="bubble">
                            <div class="message">{{ $comment->comment }}</div>
                            <div class="meta">
                                <span class="who">{{ $author }}</span>
                                @if ($comment->created_at)
                                    <span class="dot">·</span>
                                    <span class="time" title="{{ $comment->created_at->format('d-m-Y H:i') }}">{{ $comment->created_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canComment)
            <form wire:submit.prevent="addComment" class="fi-pirep-detail-v2-chat-compose">
                <div class="fi-pirep-detail-v2-chat-compose-row">
                    <textarea
                        wire:model="newComment"
                        class="fi-input"
                        rows="1"
                        placeholder="{{ __('common.add_a_comment') }}"
                    ></textarea>
                    <button
                        type="submit"
                        class="fi-btn fi-color-primary"
                        wire:loading.attr="disabled"
                        wire:target="addComment"
                    >
                        <span wire:loading.remove wire:target="addComment">{{ __('common.submit') }}</span>
                        <span wire:loading wire:target="addComment">…</span>
                    </button>
                </div>
                @error('newComment')
                    <div class="fi-pirep-detail-v2-chat-error">{{ $message }}</div>
                @enderror
            </form>
        @endif
    </div>
</div>
