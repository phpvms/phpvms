<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class() extends Migration
{
    /** @var array<string, string> */
    private const array LEGACY_COLUMNS = [
        'discord' => 'discord_id',
        'vatsim'  => 'vatsim_id',
        'ivao'    => 'ivao_id',
    ];

    public function up(): void
    {
        foreach (self::LEGACY_COLUMNS as $connectionId => $column) {
            $this->backfillConnection($connectionId, $column);
        }
    }

    public function down(): void
    {
        foreach (self::LEGACY_COLUMNS as $connectionId => $column) {
            DB::table('users')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($users) use ($connectionId, $column): void {
                    foreach ($users as $user) {
                        DB::table('user_identities')
                            ->where('user_id', $user->id)
                            ->where('connection_id', $connectionId)
                            ->where('provider_user_id', $user->{$column})
                            ->delete();
                    }
                });

            DB::table('user_identity_conflicts')->where('connection_id', $connectionId)->delete();
        }
    }

    private function backfillConnection(string $connectionId, string $column): void
    {
        $duplicateSubjects = DB::table('users')
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column)
            ->all();

        foreach ($duplicateSubjects as $subject) {
            $userIds = DB::table('users')->where($column, $subject)->orderBy('id')->pluck('id')->all();
            $this->recordConflict($connectionId, (string) $subject, $userIds, 'duplicate-provider-subject');
        }

        DB::table('users')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->when($duplicateSubjects !== [], fn ($query) => $query->whereNotIn($column, $duplicateSubjects))
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($connectionId, $column): void {
                foreach ($users as $user) {
                    $subject = (string) $user->{$column};
                    $subjectOwner = DB::table('user_identities')
                        ->where('connection_id', $connectionId)
                        ->where('provider_user_id', $subject)
                        ->value('user_id');
                    $connectionSubject = DB::table('user_identities')
                        ->where('user_id', $user->id)
                        ->where('connection_id', $connectionId)
                        ->value('provider_user_id');

                    if ($subjectOwner !== null || $connectionSubject !== null) {
                        $userIds = array_values(array_unique(array_filter([
                            (int) $user->id,
                            $subjectOwner === null ? null : (int) $subjectOwner,
                        ])));
                        $this->recordConflict($connectionId, $subject, $userIds, 'existing-identity-conflict');

                        continue;
                    }

                    DB::table('user_identities')->insert([
                        'user_id'          => $user->id,
                        'connection_id'    => $connectionId,
                        'provider_user_id' => $subject,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            });
    }

    /** @param array<int, int> $userIds */
    private function recordConflict(string $connectionId, string $subject, array $userIds, string $reason): void
    {
        DB::table('user_identity_conflicts')->insert([
            'connection_id'    => $connectionId,
            'provider_user_id' => $subject,
            'user_ids'         => json_encode($userIds, JSON_THROW_ON_ERROR),
            'reason'           => $reason,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        Log::warning('Legacy social identity ownership conflict', [
            'connection_id'    => $connectionId,
            'provider_user_id' => $subject,
            'user_ids'         => $userIds,
            'reason'           => $reason,
        ]);
    }
};
