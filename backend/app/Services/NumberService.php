<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Gap-free sequences and the portal's numbering formats (tracking no., application code, LA case no.). */
class NumberService
{
    public function next(string $scope, string $year): int
    {
        return DB::transaction(function () use ($scope, $year) {
            $row = DB::table('number_sequences')->where('scope', $scope)->where('year', $year)->lockForUpdate()->first();
            if (! $row) {
                DB::table('number_sequences')->insert(['scope' => $scope, 'year' => $year, 'last_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
                return 1;
            }
            DB::table('number_sequences')->where('id', $row->id)->update(['last_value' => $row->last_value + 1, 'updated_at' => now()]);
            return $row->last_value + 1;
        });
    }

    public static function fiscalYear(?\Carbon\Carbon $d = null): string
    {
        $d ??= now();
        $start = $d->month >= config('larms.fiscal_year_start_month', 7) ? $d->year : $d->year - 1;
        return $start . '-' . substr((string) ($start + 1), 2);          // 2025-26
    }

    /** SA-26/10/00008 */
    public function trackingNo(): string
    {
        $yy = now()->format('y'); $mm = now()->format('m');
        return sprintf('SA-%s/%s/%05d', $yy, $mm, $this->next('tracking', $yy . $mm));
    }

    /** SA26/_SYL_PU_LA_00008 (PU = public body, PR = private) */
    public function appCode(string $districtShort, string $bodyType, int $serial): string
    {
        return sprintf('SA%s/_%s_%s_LA_%05d', now()->format('y'), $districtShort, $bodyType === 'private' ? 'PR' : 'PU', $serial);
    }

    /** LA-SYL-09/2025-26 */
    public function laCaseNo(string $districtShort): string
    {
        $fy = self::fiscalYear();
        return sprintf('LA-%s-%02d/%s', $districtShort, $this->next('la:' . $districtShort, $fy), $fy);
    }

    public function noticeNo(string $type, string $laCase): string
    {
        return $laCase . '/' . str_replace('_', '(', $type) . ')/' . $this->next('notice:' . $type, $laCase);
    }
}
