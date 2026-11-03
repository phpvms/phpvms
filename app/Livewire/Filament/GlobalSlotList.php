<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Enums\GlobalSlotPosition;
use App\Enums\GlobalSlotType;
use App\Features\Themes\ThemeOutput;
use App\Filament\Pages\GlobalEdits;
use App\Models\GlobalSlot;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * An ordered list of {@see GlobalSlot} rows for one owner `key`: drag to
 * re-order, switch rows on and off, filter by slot, and add, edit or delete a
 * row in a modal with a code editor that follows the row's type.
 *
 * Mount it from a Filament page view with the Livewire Blade tag:
 *
 *     <livewire:filament.global-slot-list key="global" :can-edit="$canEdit" />
 *
 * When `$canEdit` is false the list is read-only: the actions, the toggle and
 * the re-ordering are refused on the server, not only hidden. Both properties
 * are locked, so a client cannot change them.
 */
final class GlobalSlotList extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public string $key = GlobalSlot::SITE_KEY;

    #[Locked]
    public bool $canEdit = true;

    public function mount(string $key = GlobalSlot::SITE_KEY, bool $canEdit = true): void
    {
        $this->key = $key;
        $this->canEdit = $canEdit;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => GlobalSlot::query()->where('key', $this->key))
            ->defaultSort('order')
            ->reorderable('order', fn (): bool => $this->mayEdit())
            ->afterReordering(fn () => ThemeOutput::bump())
            ->paginated(false)
            ->emptyStateHeading(__('filament.global_slots.empty'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.global_slots.name'))
                    ->searchable(),
                TextColumn::make('slot')
                    ->label(__('filament.global_slots.slot'))
                    ->formatStateUsing(fn (string $state): string => GlobalSlotPosition::tryFrom($state)?->getLabel() ?? $state)
                    ->badge(),
                TextColumn::make('type')
                    ->label(__('filament.global_slots.type'))
                    ->badge(),
                ToggleColumn::make('enabled')
                    ->label(__('filament.global_slots.enabled'))
                    ->disabled(fn (): bool => !$this->mayEdit()),
            ])
            ->filters([
                SelectFilter::make('slot')
                    ->label(__('filament.global_slots.slot'))
                    ->options(GlobalSlotPosition::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('filament.global_slots.create'))
                    ->model(GlobalSlot::class)
                    ->authorize(fn (): bool => $this->mayEdit())
                    ->schema($this->formSchema())
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'key'   => $this->key,
                        'order' => (int) GlobalSlot::query()->where('key', $this->key)->max('order') + 1,
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(__('filament.global_slots.edit'))
                    ->authorize(fn (): bool => $this->mayEdit())
                    ->schema($this->formSchema()),
                DeleteAction::make()
                    ->authorize(fn (): bool => $this->mayEdit()),
            ]);
    }

    /**
     * The mounted flag and the current user's permission, checked live so a
     * revoked permission stops writes on a page that is already open.
     */
    private function mayEdit(): bool
    {
        return $this->canEdit && GlobalEdits::canEdit();
    }

    public function render(): View
    {
        return view('livewire.filament.global-slot-list');
    }

    /**
     * @return array<int, mixed>
     */
    private function formSchema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('filament.global_slots.name'))
                ->required()
                ->maxLength(255),
            Select::make('slot')
                ->label(__('filament.global_slots.slot'))
                ->options(GlobalSlotPosition::class)
                ->required(),
            Select::make('type')
                ->label(__('filament.global_slots.type'))
                ->options(GlobalSlotType::class)
                ->default(GlobalSlotType::HTML)
                ->required()
                ->live(),
            CodeEditor::make('value')
                ->label(__('filament.global_slots.value'))
                ->language(function (Get $get): ?Language {
                    $type = $get('type');

                    return ($type instanceof GlobalSlotType ? $type : GlobalSlotType::tryFrom((string) $type))?->getEditorLanguage();
                }),
        ];
    }
}
