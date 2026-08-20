<?php

namespace App\Filament\Resources\Airlines\Schemas;

use App\Features\Assets\AssetService;
use App\Filament\Concerns\AutosavesFields;
use App\Models\Airline;
use App\Models\Asset;
use App\Services\ImageUploadService;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\ISO3166\ISO3166;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AirlineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.airline_information'))
                    ->schema([
                        TextInput::make('icao')
                            ->label('ICAO')
                            ->required()
                            ->string()
                            ->maxLength(8),

                        TextInput::make('iata')
                            ->label('IATA')
                            ->string()
                            ->maxLength(8),

                        TextInput::make('callsign')
                            ->label(__('flights.callsign'))
                            ->string(),

                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),

                        Select::make('country')
                            ->label(label: __('common.country'))
                            ->options(collect(new ISO3166()->all())->mapWithKeys(fn (array $item, string $key): array => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                            ->searchable()
                            ->native(false),

                        Toggle::make('active')
                            ->label(label: __('common.active'))
                            ->inline()
                            ->onColor('success')
                            ->onIcon(Phosphor::CheckLight)
                            ->offColor('danger')
                            ->offIcon(Phosphor::XLight),

                        Toggle::make('low_cost')
                            ->label(__('filament.airline_low_cost'))
                            ->helperText(__('filament.airline_low_cost_hint'))
                            ->inline()
                            ->onColor('success')
                            ->onIcon(Phosphor::CheckLight)
                            ->offColor('danger')
                            ->offIcon(Phosphor::XLight),
                    ])
                    ->columnSpanFull()
                    ->columns(3),

                Section::make(__('filament.airline_logo'))
                    ->schema([
                        self::logoUpload(),

                        Image::make(
                            url: fn (Get $get, ?Airline $record): string => (string) self::previewUrl($get('logo'), $record),
                            alt: __('filament.airline_logo'),
                        )
                            ->imageHeight('10rem')
                            ->alignCenter()
                            ->visible(fn (Get $get, ?Airline $record): bool => filled(self::previewUrl($get('logo'), $record))),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    /**
     * Drag-and-drop logo upload. On an existing airline the file is stored and
     * the record updated as soon as it is dropped; on a new airline there is no
     * record to write to yet, so it is saved along with the rest of the form.
     */
    private static function logoUpload(): FileUpload
    {
        return FileUpload::make('logo')
            ->label('')
            ->image()
            ->acceptedFileTypes(['image/png', 'image/svg+xml'])
            // The preview lives in the other column of this card, so the drop
            // zone stays a drop zone.
            ->previewable(false)
            // Staging only. persistLogo() moves the file into an `airline-logo`
            // asset and deletes it from here; the private disk keeps an
            // unreviewed upload out of reach in between.
            ->disk(Asset::STORAGE_LOCAL)
            ->directory(Asset::PATH_PREFIX.'/staging')
            // Deterministic staging name, so an abandoned upload is overwritten
            // by the next one rather than accumulating. A record that has not
            // been created yet has no id to use, so it falls back to a ULID.
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file, ?Airline $record): string => ($record->id ?? Str::ulid()).'.'.strtolower($file->getClientOriginalExtension())
            )
            // Converts to WebP through the shared upload service instead of
            // storing whatever format was dropped; see ImageUploadService.
            ->saveUploadedFileUsing(
                fn (FileUpload $component, TemporaryUploadedFile $file): string => app(ImageUploadService::class)->storeFilamentUpload($component, $file)
            )
            // The column may hold an external URL, which has no file on our disk
            // for the default resolver to stat. Hand those to the preview as-is.
            ->getUploadedFileUsing(function (FileUpload $component, string $file): ?array {
                if (str_contains($file, '://') || str_starts_with($file, '//')) {
                    return [
                        'name' => basename((string) (parse_url($file, PHP_URL_PATH) ?: $file)),
                        'size' => 0,
                        'type' => null,
                        'url'  => $file,
                    ];
                }

                $disk = $component->getDisk();

                if (!$disk->exists($file)) {
                    return null;
                }

                return [
                    'name' => basename($file),
                    'size' => $disk->size($file),
                    'type' => rescue(fn (): ?string => $disk->mimeType($file) ?: null, null, report: false),
                    'url'  => $disk->url($file),
                ];
            })
            ->live()
            ->afterStateUpdated(function (FileUpload $component, $livewire): void {
                // Edit page autosaves through the shared AutosavesFields trait
                // (its getState() dehydration moves the upload onto the disk,
                // and its re-entrancy guard absorbs the refire that triggers).
                // The Create page has no record yet — the logo rides the
                // normal form submit there.
                if (in_array(AutosavesFields::class, class_uses_recursive($livewire), true)) {
                    $livewire->runAutosave($component);
                }
            });
    }

    /**
     * URL for the preview pane. The upload field's state is a keyed array while
     * a file is in flight, so unwrap it and fall back to the saved record when
     * there is nothing usable in the form yet.
     */
    private static function previewUrl(mixed $state, ?Airline $record): ?string
    {
        $staged = Arr::first(Arr::wrap($state));

        // A freshly dropped file is still in staging on the private disk, which
        // has no URL. The saved asset is the only thing that renders, so fall
        // through to it and let the autosave swap the preview a moment later.
        if (!is_string($staged) || blank($staged)) {
            return $record?->logo_url;
        }

        return $record?->refresh()->logo_url;
    }

    /**
     * Store the dropped logo as the airline's `airline-logo` asset, so an upload
     * does not wait on the form being submitted. Called from EditAirline's
     * persistAutosavedField (the trait notifies).
     */
    public static function persistLogo(?Airline $record, ?string $staged): void
    {
        if (!$record instanceof Airline || !$record->exists) {
            return;
        }

        $assets = app(AssetService::class);

        // Clearing the field removes the mark outright — the row and its file
        // are one thing. The external-URL column is left alone; it is a
        // different way of having a logo, not this one.
        if (blank($staged)) {
            $assets->find(Asset::SLOT_AIRLINE_LOGO, $record->icao)?->delete();
            $record->unsetRelation('logoAsset');

            return;
        }

        $disk = Storage::disk(Asset::STORAGE_LOCAL);

        // ImageUploadService has already converted to WebP and sanitised any
        // SVG on the way into staging; AssetService decides where it lives.
        $assets->storeContents(
            (string) $disk->get($staged),
            Asset::SLOT_AIRLINE_LOGO,
            $record->icao,
            name: $record->icao,
            userId: auth()->id(),
            // Airline marks render on public flight pages, so they are fetched
            // without a session.
            storage: (string) config('filesystems.public_files'),
        );

        $disk->delete($staged);

        // The relation was resolved before the asset existed; drop the memo so
        // the preview reads the new one rather than the miss.
        $record->unsetRelation('logoAsset');
    }
}
