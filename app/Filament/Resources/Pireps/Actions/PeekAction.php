<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pireps\Actions;

use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Pirep;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Read-only slide-over for a PIREP row: the list's recordAction. Header strip
 * carries prev/next through the current table page, "Open full page" and the
 * Accept/Reject decision (the same actions as the view page).
 */
class PeekAction
{
    public const string NAME = 'peek';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(__('filament-actions::view.single.label'))
            ->icon(Phosphor::EyeLight)
            ->slideOver()
            // Read-only: leaving via "Open full page" loses nothing, so skip
            // the vendor's leave-page prompt for open actions.
            ->unsavedChangesAlert(false)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading(fn (Pirep $record): string => $record->ident)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalFooterActionsAlignment(Alignment::Start)
            ->extraModalWindowAttributes(['class' => 'pirep-panel'])
            ->extraModalOverlayAttributes(['class' => 'pirep-panel-overlay'])
            // Accept/Reject are nested actions so they keep their own
            // authorisation and visibility; the content view renders them in
            // the strip and the theme hides the footer that would repeat them.
            ->modalFooterActions(fn (): array => [
                AcceptAction::make()->extraAttributes(['data-pirep-decide' => 'accept']),
                RejectAction::make()->extraAttributes(['data-pirep-decide' => 'reject']),
            ])
            ->modalContent(function (Pirep $record, Action $action, HasTable $livewire): Htmlable {
                $record = $record->fresh(['user', 'aircraft', 'dpt_airport', 'arr_airport']) ?? $record;

                return view('filament.pireps.panel.peek', [
                    'record'       => $record,
                    'eyebrow'      => self::eyebrow($record),
                    'openUrl'      => PirepResource::getUrl('view', ['record' => $record]),
                    'decisions'    => $action->getVisibleModalFooterActions(),
                    'navigation'   => self::navigation($livewire, $record),
                    'unitDistance' => setting('units.distance'),
                    'unitFuel'     => setting('units.fuel'),
                ]);
            });
    }

    /**
     * "pilot · filed 3 Nov 14:02Z via vmsACARS" (the view page's subheading).
     */
    public static function eyebrow(Pirep $record): string
    {
        $filed = $record->submitted_at
            ? 'filed '.$record->submitted_at->format('j M H:i').'Z'
            : null;

        $sourceLabel = filled($record->source_name)
            ? $record->source?->getLabel().' · '.$record->source_name
            : $record->source?->getLabel();

        if (filled($sourceLabel)) {
            $filed = filled($filed) ? $filed.' via '.$sourceLabel : 'via '.$sourceLabel;
        }

        return implode(' · ', array_filter([$record->user?->name, $filed], filled(...)));
    }

    /**
     * Position and neighbours of the record within the table's current page.
     *
     * @return array{position: int, total: int, previous: string|null, next: string|null}
     */
    private static function navigation(HasTable $livewire, Pirep $record): array
    {
        $records = $livewire->getTableRecords();
        $keys = ($records instanceof Paginator ? collect($records->items()) : collect($records))
            ->map(fn (Pirep $row): string => $livewire->getTableRecordKey($row))
            ->values();

        $index = $keys->search((string) $record->getKey());

        return [
            'position' => $index === false ? 0 : $index + 1,
            'total'    => $keys->count(),
            'previous' => $index === false ? null : $keys->get($index - 1),
            'next'     => $index === false ? null : $keys->get($index + 1),
        ];
    }
}
