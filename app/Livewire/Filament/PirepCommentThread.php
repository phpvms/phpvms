<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Models\Pirep;
use App\Models\PirepComment;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Chat-style PIREP comment thread.
 *
 * Renders the pilot note + existing comments as a read-only chat feed, with
 * a compact compose box at the bottom for posting a new comment. Authorization
 * is delegated to PirepCommentPolicy so the same `update:pirep` gate that
 * Filament uses for the resource also gates comment creation here.
 */
final class PirepCommentThread extends Component
{
    public Pirep $record;

    #[Validate('required|string|max:5000')]
    public string $newComment = '';

    public function mount(Pirep $record): void
    {
        $this->record = $record;
        $this->record->loadMissing('comments.user', 'user');
    }

    public function addComment(): void
    {
        if (!Gate::allows('create', PirepComment::class)) {
            Notification::make()
                ->title(__('common.not_authorized'))
                ->danger()
                ->send();

            return;
        }

        $this->validate();

        PirepComment::create([
            'pirep_id' => $this->record->id,
            'user_id'  => auth()->id(),
            'comment'  => $this->newComment,
        ]);

        $this->newComment = '';

        // Refresh comments so the new row renders without a full page reload.
        $this->record->load('comments.user');

        Notification::make()
            ->title(trans_choice('pireps.comment', 1).' '.__('common.added'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.filament.pirep-comment-thread', [
            'canComment' => Gate::allows('create', PirepComment::class),
        ]);
    }
}
