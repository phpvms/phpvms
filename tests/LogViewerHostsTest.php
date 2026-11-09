<?php

namespace Tests;

use App\Models\User;

final class LogViewerHostsTest extends TestCase
{
    public function test_remote_host_credentials_are_not_returned(): void
    {
        $this->addData('base');
        config(['log-viewer.hosts' => [
            'local' => ['name' => 'Local'],
            'prod'  => [
                'name'    => 'Production',
                'host'    => 'https://example.com/log-viewer',
                'auth'    => ['token' => 'super-secret-token'],
                'headers' => ['X-Api-Key' => 'another-secret'],
            ],
        ]]);

        $res = $this->actingAs(User::find(1), 'web')
            ->getJson('/admin/log-viewer/api/hosts')
            ->assertStatus(200);

        $prod = collect($res->json('data'))->firstWhere('identifier', 'prod');
        $this->assertSame('https://example.com/log-viewer', $prod['host']);
        $this->assertArrayNotHasKey('auth', $prod);
        $this->assertArrayNotHasKey('headers', $prod);
        $this->assertStringNotContainsString('super-secret-token', $res->getContent());
        $this->assertStringNotContainsString('another-secret', $res->getContent());
    }
}
