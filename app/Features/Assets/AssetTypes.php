<?php

declare(strict_types=1);

namespace App\Features\Assets;

/**
 * What kinds of file may be stored, and what each content type is stored as.
 *
 * A registry rather than an enum because PHP enums cannot be extended: a module
 * shipping a new kind of asset — a 3D livery, an archive — could never add a
 * case to a vocabulary core declares. Same reasoning that makes `assets.source`
 * and `assets.slot` plain strings.
 *
 * Core seeds only `image`, because that is the only kind core itself serves
 * (site branding and airline marks). A module registers its own kinds from its
 * service provider:
 *
 *     app(AssetTypes::class)->register('sound', ['audio/mpeg' => 'mp3']);
 *
 * The content type is what everything keys off, and it is sniffed from the
 * bytes rather than taken from the uploader: it decides both the extension a
 * file is stored under and the Content-Type replayed when serving it. Serve a
 * component as application/octet-stream and the browser silently refuses to
 * execute it, so this map is load-bearing, not decorative.
 */
final class AssetTypes
{
    public const string IMAGE = 'image';

    /**
     * content type => [type, extension].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $contentTypes = [];

    public function __construct()
    {
        $this->register(self::IMAGE, [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/svg+xml' => 'svg',
            'image/webp'    => 'webp',
            'image/x-icon'  => 'ico',
        ]);
    }

    /**
     * Declare a kind and the content types it accepts, mapped to the extension
     * a stored file gets.
     *
     * Registering a content type twice replaces the earlier entry, so a module
     * re-registering on every worker boot is harmless.
     *
     * @param array<string, string> $contentTypeToExtension
     */
    public function register(string $type, array $contentTypeToExtension): void
    {
        foreach ($contentTypeToExtension as $contentType => $extension) {
            $this->contentTypes[strtolower($contentType)] = [$type, $extension];
        }
    }

    /** The kind this content type belongs to, or null when nothing accepts it. */
    public function typeFor(string $contentType): ?string
    {
        return $this->contentTypes[strtolower($contentType)][0] ?? null;
    }

    /**
     * The extension a file of this content type is stored under, or null when
     * it is not accepted. Callers MUST reject on null rather than guess: a
     * wrong extension is a file the consumer cannot serve correctly.
     */
    public function extensionFor(string $contentType): ?string
    {
        return $this->contentTypes[strtolower($contentType)][1] ?? null;
    }

    public function accepts(string $contentType): bool
    {
        return isset($this->contentTypes[strtolower($contentType)]);
    }

    /**
     * The content type and extension a kind stores under when the CALLER names
     * the kind, instead of the bytes naming themselves.
     *
     * Needed because sniffing cannot identify a text format: a stylesheet reads
     * as `text/plain`, and a script reads as `application/javascript` only
     * sometimes. For those the caller says what it is storing, and the content
     * type still comes from this registry rather than from anything the caller
     * handed over — which is the half of "do not trust the uploader" that has
     * to survive, since content_type is replayed when the asset is served.
     *
     * The first content type registered for a kind is its canonical one, so
     * register the preferred spelling first.
     *
     * @return array{0: string, 1: string}|null [content type, extension]
     */
    public function canonicalFor(string $type): ?array
    {
        foreach ($this->contentTypes as $contentType => [$registeredType, $extension]) {
            if ($registeredType === $type) {
                return [(string) $contentType, $extension];
            }
        }

        return null;
    }

    /**
     * Every registered kind, for a UI that offers a filter.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return array_values(array_unique(array_column($this->contentTypes, 0)));
    }
}
