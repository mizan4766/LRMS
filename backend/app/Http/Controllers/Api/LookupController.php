<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Division;
use App\Models\LandClass;
use App\Models\Mouza;
use App\Models\RequiringBody;
use App\Models\Upazila;
use App\Models\User;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /** Everything the UI needs once after login. */
    public function bootstrap()
    {
        $steps = collect(config('steps'))->map(fn ($s, $code) => ['code' => $code, 'label' => $s['label'], 'step_no' => $s['step_no'], 'role' => $s['role'], 'phase' => $s['phase'] ?? ''])->values();
        return ['divisions' => Division::orderBy('id')->get(['id', 'code', 'name_bn']), 'districts' => District::orderBy('name_bn')->get(['id', 'division_id', 'code', 'short_code', 'name_bn']),
            'upazilas' => Upazila::orderBy('name_bn')->get(['id', 'district_id', 'name_bn']), 'land_classes' => LandClass::orderBy('id')->get(['id', 'code', 'name_bn']),
            'bodies' => RequiringBody::orderBy('name_bn')->get(['id', 'name_bn', 'name_en', 'body_type']), 'roles' => config('larms.roles'), 'survey_names' => config('larms.survey_names'),
            'body_types' => config('larms.body_types'), 'attachments' => config('attachments'), 'steps' => $steps,
            'other_keys' => \App\Services\CompensationService::OTHER_KEYS];
    }

    public function mouzas(Request $r)
    {
        return Mouza::where('upazila_id', (int) $r->query('upazila_id'))->orderBy('name_bn')->get(['id', 'name_bn', 'jl_no']);
    }

    /** Users of a role (for assignments such as "select kanungo"). */
    public function users(Request $r)
    {
        $me = $r->user();
        $q = User::where('role', (string) $r->query('role'))->where('is_active', true);
        if ($r->filled('upazila_id')) $q->where('upazila_id', (int) $r->query('upazila_id'));
        elseif ($me->district_id) $q->where(fn ($x) => $x->whereNull('district_id')->orWhere('district_id', $me->district_id));
        return $q->orderBy('name_bn')->get(['id', 'name_bn', 'designation', 'upazila_id', 'union_name']);
    }
}
