<?php

declare(strict_types=1);

namespace App\Services\Awards;

use App\Enums\AwardTrigger;
use App\Models\Award;
use InvalidArgumentException;
use JsonException;

/**
 * Awards travel between installs as plain JSON: their display fields plus
 * the rule tree exactly as stored (design D8).
 *
 * There is no intermediate format. The predecessor translated to and from
 * json-rules-engine for a parity nothing actually depended on; the stored
 * tree is already portable, and shipping it verbatim means an export can
 * never disagree with the award it came from.
 *
 * An imported document is untrusted input, so it is validated here and the
 * award arrives inactive — an admin reviews the criteria before anyone can
 * be granted anything.
 */
class AwardExport
{
    /**
     * @throws JsonException
     */
    public static function toJson(Award $award): string
    {
        return json_encode([
            'name'        => $award->name,
            'description' => $award->description,
            'image_url'   => $award->image_url,
            'trigger'     => $award->trigger?->value,
            'conditions'  => $award->rule->conditions ?? [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Create an award from an exported document. Always inactive, never
     * legacy.
     *
     * @throws InvalidArgumentException when the document is not a readable award
     */
    public static function fromJson(string $json): Award
    {
        try {
            /** @var mixed $document */
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException('Award document is not valid JSON.', 0, $jsonException);
        }

        if (!is_array($document) || !is_string($document['name'] ?? null) || !is_array($document['conditions'] ?? null)) {
            throw new InvalidArgumentException('Award document needs a name and a conditions tree.');
        }

        $award = Award::create([
            'name'        => $document['name'],
            'description' => is_string($document['description'] ?? null) ? $document['description'] : null,
            'image_url'   => is_string($document['image_url'] ?? null) ? $document['image_url'] : null,
            'trigger'     => AwardTrigger::tryFrom((string) ($document['trigger'] ?? '')) ?? AwardTrigger::Pirep,
            'active'      => 0,
        ]);

        $award->saveConditionsTree($document['conditions']);

        return $award;
    }
}
