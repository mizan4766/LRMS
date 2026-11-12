<?php

namespace App\Workflow;

use App\Models\Application;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\Task;
use App\Models\User;
use App\Services\AttachmentRules;
use App\Services\AuthorityRouter;
use App\Services\NumberService;
use Illuminate\Support\Facades\DB;

/** Application-facing facade over the engine (transactions, phases, audit). */
class WorkflowService
{
    public WorkflowEngine $engine;

    public function __construct(private EloquentStore $store, EffectExecutor $effects, private NumberService $numbers)
    {
        $guards = new GuardRunner($store, fn ($g, $t, $d) => DomainGuards::check($g, $t, $d));
        $this->engine = new WorkflowEngine(config('steps'), $store, $guards, $effects);
    }

    public static function actor(User $u): array
    {
        return ['id' => (int) $u->id, 'role' => $u->role, 'district_id' => $u->district_id ? (int) $u->district_id : null, 'upazila_id' => $u->upazila_id ? (int) $u->upazila_id : null];
    }

    /** What is missing before the applicant may submit (empty array = ready). */
    public function readiness(Application $app): array
    {
        $err = [];
        foreach (['project_name_bn' => 'প্রকল্পের নাম', 'pd_name_bn' => 'প্রতিনিধির নাম', 'pd_designation' => 'পদবি', 'pd_mobile' => 'মোবাইল', 'pd_email' => 'ইমেইল', 'district_id' => 'জেলা', 'subject' => 'বিষয়'] as $f => $label) {
            if (blank($app->$f)) $err[] = "$label আবশ্যক";
        }
        $lands = $app->lands()->with('owners')->get();
        if ($lands->isEmpty()) $err[] = 'কমপক্ষে একটি দাগ যোগ করুন';
        foreach ($lands as $l) {
            if ((float) $l->proposed_area_acre <= 0) $err[] = "দাগ {$l->dag_no}: অধিগ্রহণের প্রস্তাবিত পরিমাণ দিন";
            if ((float) $l->proposed_area_acre > (float) $l->total_area_acre + 0.00001) $err[] = "দাগ {$l->dag_no}: প্রস্তাবিত পরিমাণ দাগের মোট জমির চেয়ে বেশি";
            if ($l->owners->isEmpty()) $err[] = "দাগ {$l->dag_no}: কমপক্ষে একজন মালিকের তথ্য দিন";
        }
        $atts = Attachment::where('application_id', $app->id)->where('kind', 'proposal')->get();
        $missing = AttachmentRules::missing(config('attachments'), $app->body_type, $app->flags ?? [], $atts->pluck('slot_no')->map(fn ($x) => (int) $x)->all());
        foreach ($missing as $m) $err[] = "সংযুক্তি {$m['no']}: {$m['title']}";
        foreach ($atts as $a) if ($a->expires_on && $a->expires_on < now()->toDateString()) $err[] = "সংযুক্তি {$a->slot_no}: মেয়াদোত্তীর্ণ ({$a->expires_on})";
        return $err;
    }

    public function submit(Application $app, User $user): Application
    {
        if (! in_array($app->status, ['draft'], true)) throw new WorkflowException('আবেদনটি ইতিমধ্যে দাখিল হয়েছে');
        if ($err = $this->readiness($app)) throw new WorkflowException('আবেদন দাখিলের আগে সংশোধন করুন', $err);
        return DB::transaction(function () use ($app, $user) {
            $area = (float) $app->lands()->sum('proposed_area_acre');
            $track = $this->numbers->trackingNo();
            $serial = (int) substr($track, -5);
            $short = District::whereKey($app->district_id)->value('short_code') ?: 'XXX';
            $app->update(['tracking_no' => $track, 'app_code' => $this->numbers->appCode($short, $app->body_type, $serial), 'status' => 'submitted', 'submitted_at' => now(),
                'total_area_acre' => $area, 'authority_level' => AuthorityRouter::route($area, (float) config('larms.authority_threshold_acre')), 'phase' => config('steps.S02.phase')]);
            $this->engine->spawn($app->id, 'S02');
            AuditLog::create(['user_id' => $user->id, 'action' => 'application.submit', 'entity' => 'application', 'entity_id' => $app->id, 'created_at' => now()]);
            return $app->fresh();
        });
    }

    public function act(Task $task, string $action, array $data, User $actor): array
    {
        return DB::transaction(function () use ($task, $action, $data, $actor) {
            $res = $this->engine->act($task->id, $action, $data, self::actor($actor));
            $app = Application::find($task->application_id);
            $upd = [];
            if ($res['created']) $upd['phase'] = config('steps.' . $res['created'][0]['step'] . '.phase');
            if (in_array($app->status, ['submitted', 'under_verification'], true)) $upd['status'] = 'processing';
            if ($upd) $app->update($upd);
            AuditLog::create(['user_id' => $actor->id, 'action' => 'task.act', 'entity' => 'task', 'entity_id' => $task->id, 'data' => ['step' => $task->step_code, 'action' => $action], 'created_at' => now()]);
            return $res;
        });
    }

    public function spawn(int $appId, string $step, array $opts = []): array
    {
        return $this->engine->spawn($appId, $step, $opts);
    }
}
