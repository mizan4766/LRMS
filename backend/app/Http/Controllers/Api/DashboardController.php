<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $mine = TaskController::mineQuery($u);
        $apps = ApplicationController::visible($u);
        return ['my_tasks' => (clone $mine)->count(), 'overdue' => (clone $mine)->where('due_at', '<', now())->count(),
            'applications' => (clone $apps)->count(), 'by_status' => (clone $apps)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'recent' => (clone $apps)->orderByDesc('id')->limit(6)->get(['id', 'tracking_no', 'la_case_no', 'project_name_bn', 'status', 'phase']),
            'users' => $u->role === 'admin' ? User::count() : null,
            'pending_by_step' => in_array($u->role, ['admin', 'dc', 'adc', 'ministry'], true) ? Task::where('status', 'pending')->selectRaw('step_code, count(*) n')->groupBy('step_code')->pluck('n', 'step_code') : null];
    }
}
