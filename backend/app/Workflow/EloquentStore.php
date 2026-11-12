<?php

namespace App\Workflow;

use App\Models\Application;
use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;
use App\Services\DeadlineService;

/** MySQL implementation of the engine's Store. */
class EloquentStore implements Store
{
    public function __construct(private DeadlineService $deadlines)
    {
    }

    public static function arr(Task $t): array
    {
        $i = fn ($v) => $v === null ? null : (int) $v;
        return ['id' => (int) $t->id, 'app_id' => (int) $t->application_id, 'step' => $t->step_code, 'role' => $t->role, 'user_id' => $i($t->user_id),
            'district_id' => $i($t->district_id), 'upazila_id' => $i($t->upazila_id), 'groups' => array_values(array_filter(explode(',', (string) $t->branch_groups))),
            'parent_task_id' => $i($t->parent_task_id), 'record_id' => $i($t->record_id), 'status' => $t->status, 'completed_by' => $i($t->completed_by),
            'action' => $t->action, 'data' => $t->data];
    }

    public function getTask(int $id): array { return self::arr(Task::findOrFail($id)); }

    public function pendingTasks(int $appId): array
    {
        return Task::where('application_id', $appId)->where('status', 'pending')->get()->map(fn ($t) => self::arr($t))->all();
    }

    public function createTask(array $spec): array
    {
        $due = null;
        if (! empty($spec['sla'])) {
            $npp = (bool) Application::whereKey($spec['app_id'])->value('is_npp');
            $due = $this->deadlines->due(now(), (int) $spec['sla'][$npp ? 1 : 0]);
        }
        $t = Task::create(['application_id' => $spec['app_id'], 'step_code' => $spec['step'], 'role' => $spec['role'], 'user_id' => $spec['user_id'] ?? null,
            'district_id' => $spec['district_id'] ?? null, 'upazila_id' => $spec['upazila_id'] ?? null,
            'branch_groups' => $spec['groups'] ? ',' . implode(',', $spec['groups']) . ',' : '', 'parent_task_id' => $spec['parent_task_id'] ?? null,
            'record_id' => $spec['record_id'] ?? null, 'status' => 'pending', 'due_at' => $due]);
        return self::arr($t);
    }

    public function completeTask(int $id, array $result): void
    {
        Task::findOrFail($id)->update(['status' => 'done', 'completed_at' => now(), 'completed_by' => $result['actor_id'], 'action' => $result['action'], 'data' => $result['data']]);
    }

    public function appContext(int $appId): array
    {
        $a = Application::findOrFail($appId);
        return ['district_id' => $a->district_id ? (int) $a->district_id : null, 'upazila_ids' => $a->lands()->distinct()->pluck('upazila_id')->map(fn ($x) => (int) $x)->all(),
            'is_npp' => (bool) $a->is_npp, 'creator_id' => (int) $a->created_by];
    }

    public function userInfo(int $userId): ?array
    {
        $u = User::find($userId);
        return $u ? ['id' => (int) $u->id, 'role' => $u->role, 'district_id' => $u->district_id ? (int) $u->district_id : null, 'upazila_id' => $u->upazila_id ? (int) $u->upazila_id : null] : null;
    }

    public function log(array $e): void
    {
        TaskLog::create(['application_id' => $e['app_id'], 'task_id' => $e['task_id'], 'step_code' => $e['step'], 'action' => $e['action'], 'actor_id' => $e['actor_id'],
            'actor_role' => $e['actor_role'], 'comment' => $e['comment'], 'data' => $e['data'], 'created_at' => now()]);
    }
}
