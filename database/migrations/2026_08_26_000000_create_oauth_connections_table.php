<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_connections', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('connection_id', 64)->unique();
            $table->string('display_name');
            $table->string('provider', 64);
            $table->string('client_id', 2048)->nullable();
            $table->text('client_secret')->nullable();
            $table->json('scopes')->nullable();
            $table->text('configuration')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('login_enabled')->default(false);
            $table->boolean('registration_enabled')->default(false);
            $table->boolean('linking_enabled')->default(false);
            $table->string('managed_by', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->createLegacyConnections();
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_connections');
    }

    private function createLegacyConnections(): void
    {
        $now = now();

        foreach ($this->legacyConnections() as $connectionId => $connection) {
            $secret = $connection['client_secret'];
            $configuration = $connection['configuration'];

            DB::table('oauth_connections')->insert([
                'connection_id' => $connectionId,
                'display_name'  => $connection['display_name'],
                'provider'      => $connectionId,
                'client_id'     => $connection['client_id'],
                'client_secret' => $secret === null ? null : Crypt::encryptString($secret),
                'scopes'        => json_encode($connection['scopes'], JSON_THROW_ON_ERROR),
                'configuration' => $configuration === []
                    ? null
                    : Crypt::encryptString(json_encode($configuration, JSON_THROW_ON_ERROR)),
                'enabled'              => $connection['enabled'],
                'login_enabled'        => $connection['enabled'],
                'registration_enabled' => false,
                'linking_enabled'      => $connection['enabled'],
                'managed_by'           => null,
                'sort_order'           => $connection['sort_order'],
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }

    /**
     * @return array<string, array{
     *     display_name: string,
     *     client_id: string|null,
     *     client_secret: string|null,
     *     scopes: array<int, string>,
     *     configuration: array<string, bool|string>,
     *     enabled: bool,
     *     sort_order: int
     * }>
     */
    private function legacyConnections(): array
    {
        return [
            'discord' => [
                'display_name'  => 'Discord',
                'client_id'     => $this->nullableString(config('services.discord.client_id')),
                'client_secret' => $this->nullableString(config('services.discord.client_secret')),
                'scopes'        => $this->stringList(config('services.discord.scopes', [])),
                'configuration' => [
                    'allow_gif_avatars'        => (bool) config('services.discord.allow_gif_avatars', true),
                    'avatar_default_extension' => (string) config('services.discord.avatar_default_extension', 'png'),
                ],
                'enabled'    => (bool) config('services.discord.enabled', false),
                'sort_order' => 10,
            ],
            'vatsim' => [
                'display_name'  => 'VATSIM',
                'client_id'     => $this->nullableString(config('services.vatsim.client_id')),
                'client_secret' => $this->nullableString(config('services.vatsim.client_secret')),
                'scopes'        => $this->stringList(config('services.vatsim.scopes', [])),
                'configuration' => [
                    'test' => (bool) config('services.vatsim.test', false),
                ],
                'enabled'    => (bool) config('services.vatsim.enabled', false),
                'sort_order' => 20,
            ],
            'ivao' => [
                'display_name'  => 'IVAO',
                'client_id'     => $this->nullableString(config('services.ivao.client_id')),
                'client_secret' => $this->nullableString(config('services.ivao.client_secret')),
                'scopes'        => $this->stringList(config('services.ivao.scopes', [])),
                'configuration' => [],
                'enabled'       => (bool) config('services.ivao.enabled', false),
                'sort_order'    => 30,
            ],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array<int, string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
};
