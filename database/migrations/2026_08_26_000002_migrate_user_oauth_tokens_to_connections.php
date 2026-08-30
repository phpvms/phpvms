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
        $duplicate = DB::table('user_oauth_tokens')
            ->select(['user_id', 'provider'])
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy(['user_id', 'provider'])
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Duplicate OAuth token rows exist for user '.$duplicate->user_id.' and provider '.$duplicate->provider.'.'
            );
        }

        Schema::table('user_oauth_tokens', function (Blueprint $table): void {
            $table->renameColumn('provider', 'connection_id');
        });

        $this->transformTokens(fn (string $value): string => Crypt::encryptString($value));

        Schema::table('user_oauth_tokens', function (Blueprint $table): void {
            $table->unique(['user_id', 'connection_id'], 'user_oauth_tokens_user_connection_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_oauth_tokens', function (Blueprint $table): void {
            $table->dropUnique('user_oauth_tokens_user_connection_unique');
        });

        $this->transformTokens(fn (string $value): string => Crypt::decryptString($value));

        Schema::table('user_oauth_tokens', function (Blueprint $table): void {
            $table->renameColumn('connection_id', 'provider');
        });
    }

    private function transformTokens(callable $transform): void
    {
        DB::table('user_oauth_tokens')
            ->orderBy('id')
            ->chunkById(100, function ($tokens) use ($transform): void {
                foreach ($tokens as $token) {
                    DB::table('user_oauth_tokens')
                        ->where('id', $token->id)
                        ->update([
                            'token'         => $transform($token->token),
                            'refresh_token' => $transform($token->refresh_token),
                        ]);
                }
            });
    }
};
