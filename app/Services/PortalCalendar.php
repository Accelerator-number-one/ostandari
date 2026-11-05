<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class PortalCalendar
{
    public static function deadline(int $workingDays): Carbon
    {
        $date = now();
        $remaining = max(0, min(60, $workingDays));
        $weekends = config('portal.sla.weekend_days', [5]);
        $holidays = config('portal.sla.holidays', []);
        for ($i = 0; $remaining > 0 && $i < 730; $i++) {
            $date->addDay();
            if (! in_array($date->dayOfWeek, $weekends, true) && ! in_array($date->toDateString(), $holidays, true)) {
                $remaining--;
            }
        }
        if ($remaining) {
            throw new \RuntimeException('Invalid working calendar configuration.');
        }

        return $date;
    }
}
