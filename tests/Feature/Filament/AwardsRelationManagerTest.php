<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\AwardsRelationManager;
use App\Models\Asset;
use App\Models\Award;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    fakeAssetDisks();
});

/**
 * ImageColumn::make('image') resolves the state through Award::image(),
 * and its own ->url() closure separately resolves Award::image_url() -- two
 * accessor reads per row that both bottom out in
 * AssetService::find(SLOT_AWARD, $award->id). Without the request-life memo,
 * each read was its own query, so N rows cost 2N of them.
 */
it('renders the awards table with one asset query per row instead of one per accessor read', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $user = User::factory()->create();
    $awards = Award::factory()->count(5)->create();

    foreach ($awards as $award) {
        $user->awards()->attach($award->id);
        app(AssetService::class)->storeContents(
            ASSET_TEST_PNG,
            Asset::SLOT_AWARD,
            (string) $award->id,
            storage: (string) config('filesystems.public_files'),
        );
    }

    DB::enableQueryLog();

    Livewire::test(AwardsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass'   => EditUser::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords($awards);

    // unquoteSql(): MySQL emits backticks where SQLite and PostgreSQL emit
    // double quotes, so matching the quoted table name directly passes on two
    // drivers and silently counts zero on the third.
    $assetQueries = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains(unquoteSql($q['query']), 'select * from assets'));
    DB::disableQueryLog();

    // Bounded by row count, not by how many times Filament reads a row's
    // badge while building the table. Pre-fix (AssetService::find() querying
    // every call) this table issued 15 queries for 5 rows -- 3 per row, not
    // just the `image` state and the ->url() closure, but however many times
    // Filament re-evaluates a column per row while rendering it.
    expect($assetQueries->count())->toBe($awards->count());
});
