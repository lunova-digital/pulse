<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

enum Recurrence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Every day',
            self::Weekly => 'Every week',
            self::Monthly => 'Every month',
        };
    }

    /** The first occurrence after today, counted on from the task's current due date. */
    public function nextAfterToday(?CarbonInterface $from): Carbon
    {
        if ($this === self::Daily) {
            return today()->addDay();
        }

        $day = Carbon::instance($from ?? today())->startOfDay();

        do {
            $day = $this === self::Weekly ? $day->addWeek() : $day->addMonthNoOverflow();
        } while ($day->lte(today()));

        return $day;
    }
}
