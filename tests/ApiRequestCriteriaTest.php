<?php

namespace Tests;

use App\Models\Airport;
use App\Models\Enums\UserState;
use App\Models\User;
use App\Models\UserOAuthToken;

/**
 * Request criteria must not expose columns, relations or joins
 */
final class ApiRequestCriteriaTest extends TestCase
{
    private User $pilot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');

        foreach (['KAUS' => true, 'KJFK' => false, 'KSFO' => false] as $icao => $hub) {
            Airport::factory()->create(['id' => $icao, 'icao' => $icao, 'hub' => $hub]);
        }

        // The orderBy join needs a hub airport with an active user
        $this->pilot = User::factory()->create([
            'home_airport_id' => 'KAUS',
            'state'           => UserState::ACTIVE,
        ]);
    }

    public function test_airport_search_cannot_join_and_select_user_columns(): void
    {
        $uri = '/api/airports/search?search=icao:k&orderBy=users:hub,state|users.id&filter=users.*';
        $rows = $this->get($uri, [], $this->pilot)->assertStatus(200)->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('api_key', $row);
            $this->assertArrayNotHasKey('password', $row);
            $this->assertArrayNotHasKey('email', $row);
            $this->assertArrayHasKey('icao', $row);
        }
    }

    public function test_airport_search_cannot_eager_load_user_oauth_tokens(): void
    {
        UserOAuthToken::create([
            'user_id'       => $this->pilot->id,
            'provider'      => 'discord',
            'token'         => 'access-token',
            'refresh_token' => 'refresh-token',
        ]);

        $uri = '/api/airports/search?search=icao:kaus&with=users.oauth_tokens;users';
        $res = $this->get($uri, [], $this->pilot)->assertStatus(200);

        $this->assertArrayNotHasKey('users', $res->json('data.0'));
        $this->assertStringNotContainsString('access-token', $res->getContent());
    }

    public function test_plain_order_by_still_applies(): void
    {
        $icaos = collect($this->get('/api/airports/search?search=icao:k&orderBy=icao&sortedBy=desc', [], $this->pilot)
            ->assertStatus(200)
            ->json('data'))
            ->pluck('icao')
            ->all();

        $this->assertGreaterThan(1, count($icaos));
        $sorted = $icaos;
        rsort($sorted);
        $this->assertSame($sorted, $icaos);
    }

    public function test_page_size_is_capped_at_the_configured_limit(): void
    {
        $max = (int) config('repository.pagination.limit');
        $meta = $this->get('/api/airports/search?search=icao:k&limit='.($max * 100), [], $this->pilot)
            ->assertStatus(200)
            ->json('meta');

        $this->assertSame($max, (int) $meta['per_page']);
    }

    public function test_search_fields_outside_the_searchable_list_are_ignored(): void
    {
        $search = fn (string $key) => collect($this->get('/api/airports/search?search=users.api_key:'.$key.'&searchFields=users.api_key:like&searchJoin=and', [], $this->pilot)
            ->assertStatus(200)
            ->json('data'))
            ->pluck('icao')
            ->all();

        $this->assertSame($search('no-such-key'), $search(substr($this->pilot->api_key, 0, 4)));
    }

    public function test_searchable_fields_still_apply(): void
    {
        $icaos = collect($this->get('/api/airports/search?search=kj&searchFields=icao:like', [], $this->pilot)
            ->assertStatus(200)
            ->json('data'))
            ->pluck('icao')
            ->all();

        $this->assertSame(['KJFK'], $icaos);
    }
}
