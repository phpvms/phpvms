<?php

declare(strict_types=1);

namespace App\Features\Profile\Actions;

use App\Http\Requests\SearchAirportsRequest;
use App\Queries\AirportSearchQueryV1;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Airport lookup for a typeahead (home airport, PIREP departure/arrival).
 *
 * Runs the query behind `GET /api/airports/search` without the HTTP hop, so
 * both give the same matches. Fewer than two characters finds nothing.
 */
final class SearchAirports
{
    public const int MIN_TERM_LENGTH = 2;

    /**
     * @return list<array{value: string, label: string}> airport id and "ICAO - name"
     */
    public function handle(string $term, bool $hubsOnly = false, int $limit = 20): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        $request = SearchAirportsRequest::create(
            app(UrlGenerator::class)->to('/api/airports/search'),
            'GET',
            array_filter(['search' => $term, 'limit' => $limit, 'hubs' => $hubsOnly ? '1' : null]),
        );
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return new AirportSearchQueryV1($request)
            ->build()
            ->limit($limit)
            ->get(['id', 'icao', 'name'])
            ->map(fn ($airport): array => [
                'value' => (string) $airport->id,
                'label' => $airport->icao.' - '.$airport->name,
            ])
            ->all();
    }
}
