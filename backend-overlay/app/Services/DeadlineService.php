<?php

namespace App\Services;

use Carbon\Carbon;

/** Working-day arithmetic (weekend + holidays). Start day is not counted. Pure unless holidays are loaded from the DB. */
class DeadlineService
{
    public function __construct(private ?array $weekend = null)
    {
        $this->weekend ??= function_exists('config') ? config('larms.weekend_days', [5, 6]) : [5, 6];
    }

    public function isOff(Carbon $d, array $holidays = []): bool
    {
        return in_array($d->dayOfWeek, $this->weekend, true) || in_array($d->toDateString(), $holidays, true);
    }

    public function addWorkingDays(Carbon $start, int $n, array $holidays = []): Carbon
    {
        $d = $start->copy()->startOfDay(); $c = 0;
        while ($c < $n) { $d->addDay(); if (! $this->isOff($d, $holidays)) $c++; }
        return $d->endOfDay();
    }

    /** Working-day due date using the holidays table. */
    public function due(Carbon $start, int $n): Carbon
    {
        $h = \App\Models\Holiday::whereBetween('holiday_date', [$start->toDateString(), $start->copy()->addDays($n * 2 + 30)->toDateString()])
            ->pluck('holiday_date')->map(fn ($x) => Carbon::parse($x)->toDateString())->all();
        return $this->addWorkingDays($start, $n, $h);
    }
}
