<?php

declare(strict_types=1);

use App\Features\Assets\AssetTypes;
use App\Models\Asset;
use App\Models\User;
use App\Traits\HasAssets;

/**
 * A model using the trait. User is the stand-in because `user` is a slot core
 * already declares and a user owns its own key — the two things the trait
 * needs. Ranks get the trait in a later step.
 */
class UserWithAsset extends User
{
    use HasAssets;

    public function assetSlot(): string
    {
        return Asset::SLOT_USER;
    }
}

function linkAsset(string $slot, string $key, string $url): Asset
{
    return Asset::create([
        'key'         => $key,
        'slot'        => $slot,
        'type'        => AssetTypes::IMAGE,
        'path'        => $url,
        'storage'     => Asset::STORAGE_URL,
        'last_update' => 'x',
        'size'        => 0,
    ]);
}

/** A user of the trait-using subclass; the factory only knows the parent. */
function userWithAsset(): UserWithAsset
{
    return UserWithAsset::query()->findOrFail(User::factory()->create()->id);
}

it('resolves the URL of the asset keyed by the model', function (): void {
    $user = userWithAsset();
    $asset = linkAsset(Asset::SLOT_USER, (string) $user->id, 'https://cdn.example.com/avatar.png');

    // Guard: another user's asset in the same slot must not answer for this one.
    linkAsset(Asset::SLOT_USER, (string) ($user->id + 1), 'https://cdn.example.com/someone-else.png');

    expect($user->assetUrl())->toBe($asset->url())
        ->and($user->assetUrl())->toBe('https://cdn.example.com/avatar.png');
});

it('resolves null when the model has no asset', function (): void {
    expect(userWithAsset()->assetUrl())->toBeNull();
});

it('returns null from Asset::getUrl for an unknown slot and key', function (): void {
    linkAsset(Asset::SLOT_BRANDING, 'logo', 'https://cdn.example.com/logo.png');

    expect(Asset::getUrl(Asset::SLOT_BRANDING, 'nope'))->toBeNull()
        ->and(Asset::getUrl('no-such-slot', 'logo'))->toBeNull()
        // Guard: the pair that does exist resolves, so the nulls above are the
        // lookup missing and not the lookup being broken.
        ->and(Asset::getUrl(Asset::SLOT_BRANDING, 'logo'))->toBe('https://cdn.example.com/logo.png');
});
