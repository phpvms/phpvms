<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Support;

use Closure;

/**
 * Orders timeline items by their offset in minutes from 0 and stacks the items
 * that share an offset, for `<x-vms::timeline.entry>`.
 *
 * Timed items come first, lowest offset first; items with the same offset form
 * one stack, in list order. Items without an offset follow in list order, one
 * stack each, so they never stack with each other.
 */
final class TimelineStack
{
    /**
     * @template T
     *
     * @param  iterable<T>      $items
     * @param  Closure(T): ?int $offset
     * @return list<list<T>>
     */
    public static function stack(iterable $items, Closure $offset): array
    {
        $timed = [];
        $untimed = [];

        foreach ($items as $item) {
            $minutes = $offset($item);

            if ($minutes === null) {
                $untimed[] = [$item];

                continue;
            }

            $timed[$minutes][] = $item;
        }

        ksort($timed);

        return [...array_values($timed), ...$untimed];
    }

    /**
     * An offset as `+H:MM`, or `-H:MM` before 0.
     */
    public static function label(int $minutes): string
    {
        $absolute = abs($minutes);

        return ($minutes < 0 ? '-' : '+').intdiv($absolute, 60).':'.str_pad((string) ($absolute % 60), 2, '0', STR_PAD_LEFT);
    }
}
