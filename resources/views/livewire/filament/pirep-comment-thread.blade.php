@php
    /** @var \App\Models\Pirep $record */
    /** @var bool $canComment */
    $record = $this->record;

    $initials = function (?string $name): string {
        $letters = collect(explode(' ', trim((string) $name)))
            ->filter()
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');

        return $letters === '' ? '?' : $letters;
    };

    $hasNote = filled($record->notes);
    $comments = $record->comments ?? collect();
@endphp

{{-- Notes & comments as the mockup's queue rows (pirep.html:811-826): initials
     flag, author + message text, short date. Composer is the panel foot. --}}
<div>
    <div class="queue">
        @if (! $hasNote && $comments->isEmpty())
            <div class="queue__item queue__item--empty">
                <span class="queue__text">
                    <span>{{ __('common.no_notes_or_comments') }}</span>
                </span>
            </div>
        @else
            {{-- Pilot note pinned on top, tagged so it reads apart from staff comments. --}}
            @if ($hasNote)
                <div class="queue__item cursor-default">
                    <span class="queue__flag queue__flag--info">{{ $initials($record->user?->name) }}</span>
                    <span class="queue__text">
                        <strong>{{ $record->user?->name ?? '—' }}
                            <span class="chip chip--mute chip--plain ml-1">{{ __('common.pilot_note') }}</span></strong>
                        <span>{!! $record->notes !!}</span>
                    </span>
                    @if ($record->submitted_at)
                        <span class="queue__when" title="{{ $record->submitted_at->format('d-m-Y H:i') }}">{{ $record->submitted_at->format('j M') }}</span>
                    @endif
                </div>
            @endif

            @foreach ($comments as $comment)
                <div class="queue__item cursor-default">
                    <span class="queue__flag queue__flag--mute">{{ $initials($comment->user?->name) }}</span>
                    <span class="queue__text">
                        <strong>{{ $comment->user?->name ?? '—' }}</strong>
                        <span>{{ $comment->comment }}</span>
                    </span>
                    @if ($comment->created_at)
                        <span class="queue__when" title="{{ $comment->created_at->format('d-m-Y H:i') }}">{{ $comment->created_at->format('j M') }}</span>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    @if ($canComment)
        <form wire:submit.prevent="addComment" class="panel__foot">
            <span class="field flex-1">
                <label class="sr-only" for="pirep-comment">{{ __('common.add_a_comment') }}</label>
                <input
                    id="pirep-comment"
                    type="text"
                    class="w-full"
                    wire:model="newComment"
                    placeholder="{{ __('common.add_a_comment') }}"
                />
            </span>
            <button
                type="submit"
                class="fi-btn fi-color-primary"
                wire:loading.attr="disabled"
                wire:target="addComment"
            >
                <span wire:loading.remove wire:target="addComment">{{ __('common.submit') }}</span>
                <span wire:loading wire:target="addComment">…</span>
            </button>
        </form>
        @error('newComment')
            <div class="panel__foot text-(--bad)">{{ $message }}</div>
        @enderror
    @endif
</div>
