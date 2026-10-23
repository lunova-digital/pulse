<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Date/time wording used across the UI, matching the design. */
class Fmt
{
    public static function ago(CarbonInterface $at): string
    {
        $n = (int) $at->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

        return $n <= 0 ? 'today' : ($n === 1 ? 'yesterday' : "{$n}d ago");
    }

    public static function duration(int $days): string
    {
        return $days <= 0 ? 'since today' : ($days === 1 ? '1 day' : "{$days} days");
    }

    /** Fri, 30 Oct, 11:00 AM */
    public static function meeting(CarbonInterface $at): string
    {
        return $at->format('D, j M, g:i A');
    }

    /** Today, 11:00 AM / Tomorrow, 3:00 PM / Sat, 24 Oct, 5:00 PM */
    public static function relativeMeeting(CarbonInterface $at): string
    {
        $diff = (int) now()->startOfDay()->diffInDays($at->copy()->startOfDay(), false);
        $day = match ($diff) {
            0 => 'Today',
            1 => 'Tomorrow',
            default => $at->format('D, j M'),
        };

        return $day.', '.$at->format('g:i A');
    }

    /** 9 Oct 2026 */
    public static function date(CarbonInterface $at): string
    {
        return $at->format('j M Y');
    }

    /** Heading for a day group on the timeline. */
    public static function dayGroup(CarbonInterface $at): string
    {
        $n = (int) $at->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

        return $n <= 0 ? 'Today' : ($n === 1 ? 'Yesterday' : static::date($at));
    }

    /** Overdue · Thu, 22 Oct / Due today / Due tomorrow / Due Sat, 24 Oct */
    public static function due(CarbonInterface $due, bool $done): string
    {
        $n = (int) now()->startOfDay()->diffInDays($due->copy()->startOfDay(), false);

        return match (true) {
            ! $done && $n < 0 => 'Overdue · '.$due->format('D, j M'),
            $n === 0 => 'Due today',
            $n === 1 => 'Due tomorrow',
            default => 'Due '.$due->format('D, j M'),
        };
    }
}
