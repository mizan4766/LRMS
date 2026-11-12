<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Record;

/** Builds the starting lines of the estimate / roydad book from the lands, owners and the saved valuation. */
class EstimateService
{
    public function prefill(Application $app): array
    {
        $valuation = Record::where('application_id', $app->id)->where('kind', 'valuation')->latest('id')->first();
        $byLand = collect($valuation?->data['valuation'] ?? [])->keyBy(fn ($v) => (int) ($v['land_id'] ?? 0));
        $lines = [];
        foreach ($app->lands()->with('owners')->get() as $land) {
            $v = $byLand->get((int) $land->id, []);
            $n = max(1, $land->owners->count());
            foreach ($land->owners as $o) {
                $lines[] = ['land_id' => $land->id, 'land_label' => "খতিয়ান {$land->khatian_no} / দাগ {$land->dag_no}", 'owner_id' => $o->id, 'owner_name' => $o->name, 'owner_nid' => $o->nid,
                    'share' => $o->share !== null ? (float) $o->share : round(1 / $n, 6), 'area_acre' => (float) $land->proposed_area_acre,
                    'rate_per_acre' => (float) ($v['rate_per_acre'] ?? 0), 'others' => $v['others'] ?? array_fill_keys(array_keys(CompensationService::OTHER_KEYS), 0)];
            }
        }
        return ['lines' => $lines, 'tax_percent' => (float) config('larms.tax_at_source_percent')];
    }
}
