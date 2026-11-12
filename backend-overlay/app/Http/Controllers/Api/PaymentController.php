<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use Illuminate\Http\Request;

/** Payment register: deposits by the requiring body and distributions to interested persons (read-only + CSV export). */
class PaymentController extends Controller
{
    private function query(Request $r)
    {
        $u = $r->user();
        abort_unless(in_array($u->role, ['admin', 'ministry', 'dc', 'adc', 'lao', 'accounts', 'rb'], true), 403);
        $apps = ApplicationController::visible($u)->select('id');
        $q = Payment::whereIn('application_id', $apps)->orderByDesc('id');
        foreach (['kind', 'status', 'application_id'] as $f) if ($r->filled($f)) $q->where($f, $r->query($f));
        if ($r->filled('from')) $q->whereDate('paid_at', '>=', $r->query('from'));
        if ($r->filled('to')) $q->whereDate('paid_at', '<=', $r->query('to'));
        return $q;
    }

    private function decorate($rows)
    {
        $apps = Application::whereIn('id', $rows->pluck('application_id'))->get(['id', 'tracking_no', 'la_case_no', 'project_name_bn'])->keyBy('id');
        return $rows->map(fn ($p) => $p->toArray() + ['tracking_no' => $apps[$p->application_id]->tracking_no ?? null, 'la_case_no' => $apps[$p->application_id]->la_case_no ?? null,
            'project_name_bn' => $apps[$p->application_id]->project_name_bn ?? null]);
    }

    public function index(Request $r)
    {
        $rows = $this->query($r)->limit(500)->get();
        $sum = fn ($k, $st) => round((float) $rows->where('kind', $k)->whereIn('status', (array) $st)->sum('amount'), 2);
        return ['payments' => $this->decorate($rows), 'totals' => ['deposit_received' => $sum('deposit', ['received', 'verified']), 'deposit_verified' => $sum('deposit', 'verified'),
            'distributed' => $sum('distribution', 'paid')], 'gateway' => config('larms.payment_gateway')];
    }

    public function export(Request $r)
    {
        $rows = $this->decorate($this->query($r)->limit(5000)->get());
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so that Excel shows Bangla correctly
        fputcsv($out, ['ID', 'Tracking No', 'LA Case', 'Project', 'Kind', 'Payee', 'Amount', 'Method', 'Reference', 'Status', 'Paid At']);
        foreach ($rows as $p) fputcsv($out, [$p['id'], $p['tracking_no'], $p['la_case_no'], $p['project_name_bn'], $p['kind'], $p['payee_name'], $p['amount'], $p['method'], $p['reference'], $p['status'], $p['paid_at']]);
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="larms-payments-' . now()->format('Ymd') . '.csv"']);
    }
}
