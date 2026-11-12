<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompensationService;
use App\Services\EstimateService;
use App\Services\NoticeTemplates;
use App\Workflow\EffectExecutor;
use Illuminate\Http\Request;

/** Money is always calculated on the server; the UI only displays it. */
class EstimateController extends Controller
{
    public function deedRate(Request $r, CompensationService $c)
    {
        $d = $r->validate(['deeds' => 'required|array', 'deeds.*.value' => 'required|numeric|min:0', 'deeds.*.area_acre' => 'required|numeric|min:0']);
        return $c->rateFromDeeds($d['deeds']);
    }

    public function calc(Request $r, CompensationService $c)
    {
        abort_unless(in_array($r->user()->role, ['lao', 'dc', 'adc', 'admin'], true), 403);
        $d = $r->validate(['lines' => 'required|array', 'tax_percent' => 'nullable|numeric|min:0|max:100', 'body_type' => 'required|in:govt,semi_govt,autonomous,private']);
        return $c->estimate($d['lines'], $d['body_type'], (float) ($d['tax_percent'] ?? 0));
    }

    public function prefill(Request $r, int $id, EstimateService $e)
    {
        return $e->prefill(ApplicationController::find($r, $id));
    }

    public function noticePreview(Request $r, int $id, string $type)
    {
        abort_unless(in_array($type, ['4_1', '7_1', '8_3'], true), 404);
        return ['html' => NoticeTemplates::render($type, EffectExecutor::noticeContext(ApplicationController::find($r, $id)))];
    }
}
