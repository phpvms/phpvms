<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Features\Assets\AssetService;
use App\Filament\Concerns\AutosavesFields;
use App\Models\Asset;
use App\Services\ImageUploadService;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * An image field that accepts either an upload or an external URL, stored as an
 * asset under the caller's (slot, key).
 *
 * A static schema helper rather than a `Field` subclass with a Blade view: it is
 * three stock components plus visibility closures, and the app has no field
 * subclass to follow (the one custom component, {@see AirportSelect}, extends
 * `Select`). A group of stock components also keeps every Filament behaviour the
 * host page relies on — validation, dehydration, the upload's own disk handling
 * — instead of re-implementing them behind a view.
 *
 * The control is edit-only: an asset is keyed on the record, so it is hidden on
 * the `create` operation rather than staging bytes with nothing to key them on.
 *
 * The host page owns persistence, because only it knows its record's key. Wire
 * it up as:
 *
 *     // schema
 *     AssetImagePicker::make(Asset::SLOT_RANK, fn (?Rank $r): ?int => $r?->id, $disk)
 *
 *     // Edit page
 *     protected function autosaveKeys(): array
 *     {
 *         return AssetImagePicker::stateKeys(Asset::SLOT_RANK);
 *     }
 *
 *     protected function persistAutosavedField(string $key, mixed $value): void
 *     {
 *         AssetImagePicker::persist(Asset::SLOT_RANK, $this->getRecord()->id, $disk, $key, $value);
 *     }
 */
final class AssetImagePicker
{
    public const string SOURCE_UPLOAD = 'upload';

    public const string SOURCE_URL = 'url';

    /**
     * @param string  $slot    asset slot the row is written under
     * @param Closure $key     (?Model $record): int|string|null — the record's key
     * @param string  $storage disk an upload's bytes land on
     */
    public static function make(string $slot, Closure $key, string $storage, ?string $label = null): Group
    {
        [$sourceKey, $uploadKey, $urlKey] = self::stateKeys($slot);

        return Group::make([
            Radio::make($sourceKey)
                ->label($label ?? __('filament.asset_image'))
                ->options([
                    self::SOURCE_UPLOAD => __('filament.asset_image_source_upload'),
                    self::SOURCE_URL    => __('filament.asset_image_source_url'),
                ])
                ->inline()
                ->inlineLabel(false)
                ->default(self::SOURCE_UPLOAD)
                // Preselects the source the saved asset actually uses; a link
                // row opens in URL mode, everything else (including no asset at
                // all) in Upload mode.
                ->formatStateUsing(fn (?Model $record): string => self::asset($slot, $key, $record)?->isLink()
                    ? self::SOURCE_URL
                    : self::SOURCE_UPLOAD)
                ->live()
                ->columnSpanFull(),

            self::upload($uploadKey, $key)
                ->visible(fn (Get $get): bool => $get($sourceKey) !== self::SOURCE_URL),

            TextInput::make($urlKey)
                ->label(__('filament.asset_image_source_url'))
                ->url()
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get($sourceKey) === self::SOURCE_URL)
                ->formatStateUsing(function (?Model $record) use ($slot, $key): ?string {
                    $asset = self::asset($slot, $key, $record);

                    return $asset?->isLink() === true ? $asset->path : null;
                })
                ->live(onBlur: true)
                ->afterStateUpdated(self::autosave(...)),

            Image::make(
                url: fn (?Model $record): string => (string) self::previewUrl($slot, $key, $record),
                alt: $label ?? __('filament.asset_image'),
            )
                ->imageHeight('10rem')
                ->alignCenter()
                ->visible(fn (?Model $record): bool => filled(self::previewUrl($slot, $key, $record))),
        ])
            // Edit-only: on a create page there is no key to write the asset
            // under, so the control is not offered at all.
            ->hiddenOn('create')
            ->columns(2);
    }

    /**
     * The three form keys this control occupies, in order: source, upload, URL.
     *
     * A host page needs them to declare its autosave keys.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function stateKeys(string $slot): array
    {
        $prefix = str_replace('-', '_', $slot);

        return ["{$prefix}_source", "{$prefix}_upload", "{$prefix}_url"];
    }

    /**
     * Persist one of this control's form keys.
     *
     * Per key and not per control, because AutosavesFields narrows a save to the
     * field that fired it — a source flip on its own must not touch the stored
     * asset, and the field that is currently hidden holds null.
     *
     * @param int|string|null $key the record's key; nothing is written without one
     */
    public static function persist(string $slot, int|string|null $key, string $storage, string $stateKey, mixed $value): void
    {
        if (blank($key)) {
            return;
        }

        $key = (string) $key;

        [$sourceKey, $uploadKey, $urlKey] = self::stateKeys($slot);

        match ($stateKey) {
            $uploadKey => self::persistUpload($slot, $key, $storage, is_string($value) ? $value : null),
            $urlKey    => self::persistLink($slot, $key, is_string($value) ? $value : null),
            // Switching source is a UI move; the asset changes when a file or a
            // URL arrives.
            $sourceKey => null,
            default    => null,
        };
    }

    /**
     * Drag-and-drop half of the control, staged exactly as AirlineForm stages a
     * logo: onto the disk with no URL of its own, so an unreviewed upload is out
     * of reach until {@see persist()} turns it into an asset.
     */
    private static function upload(string $name, Closure $key): FileUpload
    {
        return FileUpload::make($name)
            ->label('')
            ->image()
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'])
            // The preview lives in the other column, so the drop zone stays a
            // drop zone.
            ->previewable(false)
            ->disk(Asset::STORAGE_LOCAL)
            ->directory(Asset::PATH_PREFIX.'/staging')
            // Deterministic staging name, so an abandoned upload is overwritten
            // by the next one rather than accumulating. The control is
            // edit-only, so the record — and its key — always exist here.
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file, Model $record): string => $key($record)
                    .'.'.strtolower($file->getClientOriginalExtension())
            )
            // Converts to WebP and sanitises SVG through the shared upload
            // service rather than storing whatever was dropped.
            ->saveUploadedFileUsing(
                fn (FileUpload $component, TemporaryUploadedFile $file): string => app(ImageUploadService::class)->storeFilamentUpload($component, $file)
            )
            ->live()
            ->afterStateUpdated(self::autosave(...));
    }

    /**
     * Autosave hook for a static schema helper: there is no `$this` here, so it
     * delegates to whichever page mounted the schema, and only when that page
     * autosaves at all.
     */
    private static function autosave(mixed $component, mixed $livewire): void
    {
        if (in_array(AutosavesFields::class, class_uses_recursive($livewire), true)) {
            $livewire->runAutosave($component);
        }
    }

    private static function persistUpload(string $slot, string $key, string $storage, ?string $staged): void
    {
        $assets = app(AssetService::class);

        // Clearing the field removes the asset outright — the row and its file
        // are one thing.
        if (blank($staged)) {
            $assets->find($slot, $key)?->delete();

            return;
        }

        $disk = Storage::disk(Asset::STORAGE_LOCAL);

        // A staged file that has gone missing reads as null; storing '' would
        // sniff as `application/x-empty` and blow up the autosave instead of
        // leaving the existing asset alone.
        $contents = $disk->get($staged);

        if (blank($contents)) {
            return;
        }

        $assets->storeContents(
            $contents,
            $slot,
            $key,
            userId: auth()->id(),
            storage: $storage,
        );

        $disk->delete($staged);
    }

    private static function persistLink(string $slot, string $key, ?string $url): void
    {
        $assets = app(AssetService::class);

        if (blank($url)) {
            $assets->find($slot, $key)?->delete();

            return;
        }

        $assets->storeLink($url, $slot, $key, userId: auth()->id());
    }

    private static function asset(string $slot, Closure $key, ?Model $record): ?Asset
    {
        $resolved = $key($record);

        return blank($resolved) ? null : app(AssetService::class)->find($slot, (string) $resolved);
    }

    /**
     * A freshly staged upload sits on a disk with no URL, so there is nothing to
     * render from the form state; the saved asset is the only thing that shows,
     * and the autosave swaps it a moment later.
     */
    private static function previewUrl(string $slot, Closure $key, ?Model $record): ?string
    {
        return self::asset($slot, $key, $record)?->url();
    }
}
