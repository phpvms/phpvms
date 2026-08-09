<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * Ensures a Passport personal access client exists so users can mint personal
 * access tokens. Idempotent — safe to run on every setup/deploy.
 */
class PassportSeeder extends Seeder
{
    public function __construct(private readonly ClientRepository $clients) {}

    public function run(): void
    {
        $hasPersonalClient = Client::query()
            ->get()
            ->contains(fn (Client $client): bool => $client->hasGrantType('personal_access'));

        if ($hasPersonalClient) {
            return;
        }

        $this->clients->createPersonalAccessGrantClient(
            config('app.name').' Personal Access Client'
        );
    }
}
