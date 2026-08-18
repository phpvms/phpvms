<?php

declare(strict_types=1);

namespace App\Features\Assets\Enums;

/**
 * What an asset's bytes are — as opposed to AssetSlot, which says who consumes
 * them. The same type appears in several slots: a branding logo and a gauge
 * texture are both IMAGE.
 *
 * The accepted MIME types per case are closed too, and neither the type nor the
 * content type is ever taken from the uploader. The server sniffs the file and
 * decides, because content_type drives both the extension the file is stored
 * under and the Content-Type replayed when serving it — get it wrong and a
 * component is served as application/octet-stream and silently never executes.
 */
enum AssetType: string
{
    case IMAGE = 'image';
    case SOUND = 'sound';
    case COMPONENT = 'component';
    case CSS = 'css';

    /**
     * Accepted MIME types for this kind, mapped to the extension a stored file
     * gets.
     *
     * @return array<string, string>
     */
    public function contentTypes(): array
    {
        return match ($this) {
            self::IMAGE => [
                'image/png'     => 'png',
                'image/jpeg'    => 'jpg',
                'image/svg+xml' => 'svg',
                'image/webp'    => 'webp',
                'image/x-icon'  => 'ico',
            ],
            self::SOUND => [
                'audio/mpeg' => 'mp3',
                'audio/wav'  => 'wav',
                'audio/ogg'  => 'ogg',
            ],
            self::COMPONENT => [
                'application/javascript' => 'js',
                'text/javascript'        => 'js',
            ],
            self::CSS => [
                'text/css' => 'css',
            ],
        };
    }

    public function allows(string $contentType): bool
    {
        return array_key_exists($contentType, $this->contentTypes());
    }

    /**
     * The extension a file of this content type is stored under, or null when
     * the content type is not allowed for this kind. Callers MUST reject on
     * null rather than guess: a wrong extension is a file the consumer cannot
     * serve correctly.
     */
    public function extensionFor(string $contentType): ?string
    {
        return $this->contentTypes()[$contentType] ?? null;
    }

    /**
     * The type that accepts this content type, or null if none does. Lets an
     * uploader be told what a file is rather than asked.
     */
    public static function forContentType(string $contentType): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->allows($contentType)) {
                return $case;
            }
        }

        return null;
    }
}
