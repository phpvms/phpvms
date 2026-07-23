<?php

declare(strict_types=1);

use App\Auth\RichBearerTokenResponse;
use App\Models\User;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use Spatie\Permission\Models\Role;

function invokeExtraParams(?string $userId): array
{
    $token = Mockery::mock(AccessTokenEntityInterface::class);
    $token->shouldReceive('getUserIdentifier')->andReturn($userId);

    $method = new ReflectionMethod(RichBearerTokenResponse::class, 'getExtraParams');

    return $method->invoke(new RichBearerTokenResponse(), $token);
}

it('adds the pilot roles and permissions to the token response body', function (): void {
    Role::create(['name' => 'test_role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('test_role');

    $extra = invokeExtraParams((string) $user->id);

    expect($extra['roles'])->toBe(['test_role'])
        ->and($extra)->toHaveKey('permissions')
        ->and($extra['permissions'])->toBeArray();
});

it('adds nothing for a token with no resource owner (client credentials)', function (): void {
    expect(invokeExtraParams(null))->toBe([]);
});
