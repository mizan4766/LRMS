<?php

namespace App\Services;

/**
 * Compensation per sec 9, 10 and Rules 29-31 (Schedule 29 estimate / Schedule 32 roydad book).
 *  land value  = area x rate per acre (average of the last 12 months' deeds, Rule 29)
 *  land total  = land value + 200% (government) or + 300% (private body)             [sec 9(2)]
 *  other items = structures, trees, ponds, business, machinery, others (valued separately)
 *  extra       = 100% on the valued other items                                      [sec 9(3), Rule 30(11)]
 *  tax at source is deducted from the payee's share; incidental costs are added to the estimate (Rule 31(2)).
 */
class CompensationService
{
    public const OTHER_KEYS = ['structures' => 'স্থাপনা', 'trees' => 'গাছপালা', 'ponds' => 'পুকুর/জলাশয়', 'business' => 'ব্যবসা', 'machinery' => 'মেশিনারিজ', 'others' => 'অন্যান্য'];

    /** Rule 29: average deed rate per acre; at least 3 deeds, otherwise a Schedule-26 certificate is needed. */
    public function rateFromDeeds(array $deeds): array
    {
        $value = 0.0; $area = 0.0; $n = 0;
        foreach ($deeds as $d) {
            $v = (float) ($d['value'] ?? 0); $a = (float) ($d['area_acre'] ?? 0);
            if ($v > 0 && $a > 0) { $value += $v; $area += $a; $n++; }
        }
        return ['rate_per_acre' => $area > 0 ? round($value / $area, 2) : 0.0, 'deeds' => $n, 'needs_certificate' => $n < 3];
    }

    public function line(array $in, string $bodyType, float $taxPercent = 0.0): array
    {
        $share = isset($in['share']) && $in['share'] !== '' ? (float) $in['share'] : 1.0;
        $landValue = round((float) ($in['area_acre'] ?? 0) * (float) ($in['rate_per_acre'] ?? 0) * $share, 2);
        $premiumRate = $bodyType === 'private' ? 3.0 : 2.0;
        $premium = round($landValue * $premiumRate, 2);
        $others = [];
        foreach (self::OTHER_KEYS as $k => $_) $others[$k] = round(max(0.0, (float) ($in['others'][$k] ?? 0)) * $share, 2);
        $otherSum = round(array_sum($others), 2);
        $extra = round($otherSum * 1.0, 2);
        $gross = round($landValue + $premium + $otherSum + $extra, 2);
        $tax = round($gross * $taxPercent / 100, 2);
        return ['land_id' => $in['land_id'] ?? null, 'owner_name' => $in['owner_name'] ?? '', 'owner_id' => $in['owner_id'] ?? null, 'owner_nid' => $in['owner_nid'] ?? null, 'share' => $share,
            'area_acre' => (float) ($in['area_acre'] ?? 0), 'rate_per_acre' => (float) ($in['rate_per_acre'] ?? 0),
            'land_value' => $landValue, 'premium_rate' => $premiumRate, 'premium' => $premium, 'land_total' => round($landValue + $premium, 2),
            'others' => $others, 'other_sum' => $otherSum, 'extra_100' => $extra, 'gross' => $gross, 'tax' => $tax, 'net' => round($gross - $tax, 2)];
    }

    /** Rule 31(2): govt body 3% up to 1 crore and 2% above; private body 10%. mode 'tiered' (default) or 'flat'. */
    public function incidental(float $amount, string $bodyType, string $mode = 'tiered'): float
    {
        if ($bodyType === 'private') return round($amount * 0.10, 2);
        $crore = 10000000.0;
        if ($mode === 'flat') return round($amount * ($amount <= $crore ? 0.03 : 0.02), 2);
        return round(min($amount, $crore) * 0.03 + max(0.0, $amount - $crore) * 0.02, 2);
    }

    public function estimate(array $lines, string $bodyType, float $taxPercent = 0.0, string $mode = 'tiered'): array
    {
        $out = array_map(fn ($l) => $this->line($l, $bodyType, $taxPercent), $lines);
        $gross = round(array_sum(array_column($out, 'gross')), 2);
        $tax = round(array_sum(array_column($out, 'tax')), 2);
        $inc = $this->incidental($gross, $bodyType, $mode);
        return ['lines' => $out, 'gross_total' => $gross, 'tax_total' => $tax, 'net_total' => round($gross - $tax, 2),
            'incidental' => $inc, 'estimate_total' => round($gross + $inc, 2), 'body_type' => $bodyType, 'tax_percent' => $taxPercent];
    }
}
