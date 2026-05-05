<?php

declare(strict_types=1);

use App\Models\News;

/*
 * Locks in the JSON response shape of GET /api/news.
 *
 * This is the contract external API consumers (ACARS clients, pilot apps,
 * third-party integrations) depend on. Must remain stable across the
 * repository-removal refactor (Phases 1-7).
 *
 * Note: /api/news is a public endpoint (no auth middleware), but it still
 * returns a paginated collection of NewsResource objects.
 *
 * Unlike Characterization tests, this file STAYS — it's the permanent
 * contract test for the public API.
 */

test('news list returns expected json structure', function (): void {
    // 3 items so the eager load (->with('user') in NewsController) is
    // genuinely exercised. Single-item tests previously masked an N+1
    // because preventLazyLoading didn't trip on count == 1.
    News::factory()->count(3)->create();

    $response = $this->get('/api/news');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'user_id',
                'subject',
                'body',
                'user' => [
                    'id',
                    'name',
                ],
            ],
        ],
        'links' => [
            'first',
            'last',
            'prev',
            'next',
        ],
        'meta' => [
            'current_page',
            'from',
            'last_page',
            'path',
            'per_page',
            'to',
            'total',
        ],
    ]);
});
