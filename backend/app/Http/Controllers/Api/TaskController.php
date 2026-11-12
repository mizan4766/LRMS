<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\LandClass;
use App\Models\Record;
use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;
use App\Services\EstimateService;
use App\Services\FeasibilityItems;
use App\Services\NoticeTemplates;
use App\Workflow\EffectExecutor;
use App\Workflow\EloquentStore;
use App\Workflow\WorkflowService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /** Pending tasks the user can act on: assigned to him, or role-based within his district/upazila. */
    public static function mineQuery(User $u)
    {
        $national = in_array($u->role, ['ministry', 'commissioner', 'admin'], true);
        return Task::where('status', 'pending')->where(function ($q) use ($u, $national) {
            $q->where('user_id', $u->id)->orWhere(function ($x) use ($u, $national) {
                $x->whereNull('user_id')->where('role', $u->role);
                if (! $national) $x->where(fn ($d) => $d->whereNull('district_id')->orWhere('district_id', $u->district_id));
                $x->where(fn ($d) => $d->whereNull('upazila_id')->orWhere('upazila_id', $u->upazila_id));
            });
        });
    }

    private function row(Task $t): array
    {
        $def = config("steps.{$t->step_code}");
        $a = $t->application;
        return ['id' => $t->id, 'step_code' => $t->step_code, 'step_no' => $def['step_no'], 'label' => $def['label'], 'phase' => $def['phase'] ?? '', 'due_at' => $t->due_at, 'overdue' => $t->due_at && $t->due_at < now(),
            'created_at' => $t->created_at, 'application' => ['id' => $a->id, 'tracking_no' => $a->tracking_no, 'la_case_no' => $a->la_case_no, 'project_name_bn' => $a->project_name_bn], 'status' => $t->status];
    }

    public function mine(Request $r)
    {
        $q = self::mineQuery($r->user())->with('application:id,tracking_no,la_case_no,project_name_bn')->orderByRaw('due_at IS NULL')->orderBy('due_at')->orderBy('id');
        $rows = $q->get()->map(fn ($t) => $this->row($t));
        return ['tasks' => $rows, 'count' => $rows->count(), 'overdue' => $rows->where('overdue', true)->count()];
    }

    public function history(Request $r)
    {
        return Task::where('completed_by', $r->user()->id)->with('application:id,tracking_no,la_case_no,project_name_bn')->orderByDesc('completed_at')->limit(60)->get()->map(fn ($t) => $this->row($t) + ['action' => $t->action, 'completed_at' => $t->completed_at]);
    }

    public function show(Request $r, int $id, WorkflowService $wf)
    {
        $t = Task::with('application')->findOrFail($id);
        $user = $r->user();
        $can = $wf->engine->canAct(WorkflowService::actor($user), EloquentStore::arr($t));
        if (! $can) ApplicationController::find($r, $t->application_id);   // otherwise the user must at least be allowed to see the application
        $def = config("steps.{$t->step_code}"); $app = $t->application;
        $fields = array_map(function ($f) use ($t, $user) {
            if (in_array($f['type'], ['user', 'users'], true)) {
                $q = User::where('role', $f['role'])->where('is_active', true);
                if ($t->upazila_id && in_array($f['role'], ['ulao'], true)) $q->where('upazila_id', $t->upazila_id);
                elseif ($user->district_id) $q->where(fn ($x) => $x->whereNull('district_id')->orWhere('district_id', $user->district_id));
                $f['choices'] = $q->orderBy('name_bn')->get(['id', 'name_bn', 'designation', 'upazila_id'])->all();
            }
            return $f;
        }, $def['fields'] ?? []);
        $actions = collect($def['actions'])->map(fn ($a, $k) => ['key' => $k, 'label' => $a['label'], 'style' => $a['style'] ?? 'primary',
            'fields' => $a['fields'] ?? []])->values();
        $comps = collect($fields)->pluck('component')->filter()->all();
        $ctx = ['lands' => $app->lands()->with(['owners', 'upazila:id,name_bn', 'mouza:id,name_bn', 'recordClass:id,name_bn', 'usageClass:id,name_bn'])->get(), 'classes' => LandClass::orderBy('id')->get(['id', 'name_bn']),
            'logs' => TaskLog::where('application_id', $app->id)->orderByDesc('id')->limit(6)->get(['step_code', 'action', 'actor_role', 'comment', 'created_at'])];
        foreach ($def['fields'] ?? [] as $f) {
            if (($f['component'] ?? null) === 'notice') $ctx['notice_preview'] = NoticeTemplates::render($f['notice'], EffectExecutor::noticeContext($app, ['distribution_date' => '', 'place' => '']));
        }
        if (in_array('feasibility', $comps, true)) $ctx['feasibility_items'] = FeasibilityItems::all();
        if (in_array('estimate', $comps, true)) $ctx['estimate_prefill'] = app(EstimateService::class)->prefill($app);
        if (in_array('valuation', $comps, true)) $ctx['other_keys'] = \App\Services\CompensationService::OTHER_KEYS;
        if ($t->record_id) $ctx['record'] = Record::find($t->record_id);
        if (($def['component'] ?? null) === 'deposit') $ctx['deposit_required'] = $app->deposit_required;
        if (($def['component'] ?? null) === 'deposit_verify') {
            $ctx['deposit_required'] = $app->deposit_required;
            $ctx['deposit'] = \App\Models\Payment::where('application_id', $app->id)->where('kind', 'deposit')->latest('id')->first();
        }
        if (($def['component'] ?? null) === 'refine') $ctx['refine_note'] = $app->refine_note;
        return ['task' => $this->row($t) + ['user_id' => $t->user_id, 'upazila_id' => $t->upazila_id, 'record_id' => $t->record_id, 'data' => $t->data, 'completed_at' => $t->completed_at],
            'can_act' => $can, 'step' => ['code' => $t->step_code, 'label' => $def['label'], 'step_no' => $def['step_no'], 'phase' => $def['phase'] ?? '', 'role' => $def['role'], 'component' => $def['component'] ?? null],
            'fields' => $fields, 'actions' => $actions, 'application' => ['id' => $app->id, 'tracking_no' => $app->tracking_no, 'la_case_no' => $app->la_case_no, 'project_name_bn' => $app->project_name_bn,
                'body_type' => $app->body_type, 'status' => $app->status, 'authority_level' => $app->authority_level, 'total_area_acre' => $app->total_area_acre, 'is_npp' => $app->is_npp], 'context' => $ctx];
    }

    public function act(Request $r, int $id, WorkflowService $wf)
    {
        $d = $r->validate(['action' => 'required|string', 'data' => 'nullable|array']);
        $res = $wf->act(Task::findOrFail($id), $d['action'], $d['data'] ?? [], $r->user());
        return ['ok' => true, 'created' => array_map(fn ($t) => ['id' => $t['id'], 'step' => $t['step'], 'label' => config("steps.{$t['step']}.label")], $res['created'])];
    }
}
