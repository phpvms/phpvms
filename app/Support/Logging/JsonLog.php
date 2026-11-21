<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Support\Carbon;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\Logs\Log;
use Throwable;

/**
 * The admin log viewer's reader for the JSON-line files Monolog's JsonFormatter writes.
 */
class JsonLog extends Log
{
    public static string $name = 'Laravel (JSON)';

    public static function matches(string $text, ?int &$timestamp = null, ?string &$level = null): bool
    {
        $record = self::decode($text);

        if ($record === null) {
            return false;
        }

        try {
            $timestamp = Carbon::parse($record['datetime'])->getTimestamp();
        } catch (Throwable) {
            return false;
        }

        $level = $record['level_name'];

        return true;
    }

    protected function parseText(array &$matches = []): void {}

    protected function fillMatches(array $matches = []): void
    {
        $record = self::decode((string) $this->text) ?? [];

        $this->datetime = isset($record['datetime']) ? Carbon::parse($record['datetime'])->setTimezone(LogViewer::timezone()) : null;
        $this->level = $record['level_name'] ?? null;
        $this->message = trim((string) ($record['message'] ?? ''));
        $this->context = is_array($record['context'] ?? null) ? $record['context'] : [];
        $this->extra = is_array($record['extra'] ?? null) ? $record['extra'] : [];
    }

    /**
     * @return array{datetime: string, level_name: string, message?: string, context?: mixed, extra?: mixed}|null
     */
    private static function decode(string $text): ?array
    {
        $text = trim($text);

        if (!str_starts_with($text, '{')) {
            return null;
        }

        $record = json_decode($text, true);

        if (!is_array($record) || !is_string($record['datetime'] ?? null) || !is_string($record['level_name'] ?? null)) {
            return null;
        }

        /** @var array{datetime: string, level_name: string, message?: string, context?: mixed, extra?: mixed} $record */
        return $record;
    }
}
