<?php

namespace App\Filament\Resources\Airlines\Schemas;

use App\Models\Airline;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\ISO3166\ISO3166;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AirlineForm
{
    /**
     * Re-entry guard for the upload hook (see logoUpload).
     */
    private static bool $storingLogo = false;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.airline_information'))
                    ->schema([
                        TextInput::make('icao')
                            ->label('ICAO (3LD)')
                            ->required()
                            ->string()
                            ->length(3),

                        TextInput::make('iata')
                            ->label('IATA (2LD)')
                            ->string()
                            ->length(2),

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
                            ->onIcon(Heroicon::CheckCircle)
                            ->offColor('danger')
                            ->offIcon(Heroicon::XCircle),

                        Toggle::make('low_cost')
                            ->label(__('filament.airline_low_cost'))
                            ->helperText(__('filament.airline_low_cost_hint'))
                            ->inline()
                            ->onColor('success')
                            ->onIcon(Heroicon::CheckCircle)
                            ->offColor('danger')
                            ->offIcon(Heroicon::XCircle),
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
                            ->visible(fn (Get $get, ?Airline $record): bool => filled(self::previewUrl($get('logo'), $record))),

                        Text::make(__('filament.airline_logo_empty'))
                            ->visible(fn (Get $get, ?Airline $record): bool => blank(self::previewUrl($get('logo'), $record))),
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
            ->label(__('filament.airline_logo'))
            ->helperText(__('filament.airline_logo_hint'))
            ->image()
            ->acceptedFileTypes(['image/*', 'image/svg+xml'])
            // The preview lives in the other column of this card, so the drop
            // zone stays a drop zone.
            ->previewable(false)
            ->disk(config('filesystems.public_files'))
            ->directory(Airline::LOGO_DIRECTORY)
            ->visibility('public')
            // Name the file after the airline so replacing a logo overwrites it
            // in place and the URL never changes -- logo_hash is what tells a
            // client the image behind that URL is new. A record that has not
            // been created yet has no id to use, so it falls back to a ULID.
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file, ?Airline $record): string => ($record->id ?? Str::ulid()).'.'.strtolower($file->getClientOriginalExtension())
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
            ->afterStateUpdated(function (FileUpload $component, ?Airline $record): void {
                // saveUploadedFiles() ends by firing this same hook again, so
                // guard the re-entry ourselves rather than relying on Filament
                // de-duplicating the second call.
                if (self::$storingLogo) {
                    return;
                }

                self::$storingLogo = true;

                try {
                    // A live update fires while the state is still a temporary
                    // upload; saving the component moves the file onto the disk
                    // and rewrites the state to its final path.
                    $component->saveUploadedFiles();

                    $logo = $component->getState();

                    self::persist($record, is_string($logo) ? $logo : null);
                } finally {
                    self::$storingLogo = false;
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
        $logo = Arr::first(Arr::wrap($state));

        if (!is_string($logo) || blank($logo)) {
            return $record?->logo_url;
        }

        return Airline::resolveLogoUrl($logo);
    }

    /**
     * Write the logo straight to the record so an upload does not wait on the
     * form being submitted. Skipped when the value has not actually changed,
     * which is also what stops the re-entrant call that saving the upload
     * component triggers on itself.
     */
    private static function persist(?Airline $record, ?string $logo): void
    {
        $logo = blank($logo) ? null : $logo;

        if (!$record instanceof Airline || !$record->exists || $logo === $record->logo) {
            return;
        }

        $previous = $record->logo;

        // logo_hash is stamped by the model whenever logo is set.
        $record->update(['logo' => $logo]);

        // Drop the file that was just replaced. Deterministic naming means a
        // re-upload of the same type overwrites in place, so only a changed
        // path (or a cleared logo) can leave an orphan behind.
        if (filled($previous) && str_starts_with((string) $previous, Airline::LOGO_DIRECTORY.'/')) {
            Storage::disk(config('filesystems.public_files'))->delete($previous);
        }

        Notification::make()
            ->title(__('filament.airline_logo_saved'))
            ->success()
            ->send();
    }
}
