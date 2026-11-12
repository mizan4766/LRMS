<?php

namespace App\Workflow;

/**
 * Data-driven workflow engine. The graph lives in config/steps.php; this class only interprets it:
 * role/scope checks, form validation, guards, fan-out per upazila, parallel branches with joins, effects, history.
 */
class WorkflowEngine
{
    /** @param callable|null $guards fn(string $guard, array $task, array $data, array $ctx): ?string
     *  @param callable|null $effects fn(string $effect, array $task, array $data, array $actor, array $ctx): void */
    public function __construct(private array $steps, private Store $store, private $guards = null, private $effects = null)
    {
    }

    public function definition(string $step): array
    {
        return $this->steps[$step] ?? throw new WorkflowException("Unknown step $step");
    }

    /** Create a task that is not produced by another task (application submission, citizen-initiated steps). */
    public function spawn(int $appId, string $step, array $opts = []): array
    {
        $def = $this->definition($step);
        $ctx = $this->store->appContext($appId);
        return $this->store->createTask([
            'app_id' => $appId, 'step' => $step, 'role' => $def['role'], 'user_id' => $opts['user_id'] ?? null,
            'district_id' => $ctx['district_id'], 'upazila_id' => $opts['upazila_id'] ?? null, 'groups' => $opts['groups'] ?? [],
            'parent_task_id' => $opts['parent_task_id'] ?? null, 'record_id' => $opts['record_id'] ?? null, 'sla' => $def['sla'] ?? null,
        ]);
    }

    /** May this user act on this task? */
    public function canAct(array $actor, array $task): bool
    {
        if ($task['status'] !== 'pending') return false;
        if ($task['user_id'] !== null) return (int) $task['user_id'] === (int) $actor['id'];
        if ($actor['role'] !== $task['role']) return false;
        $national = in_array($actor['role'], ['ministry', 'commissioner'], true);
        if (! $national && $task['district_id'] !== null && $actor['district_id'] !== null && (int) $task['district_id'] !== (int) $actor['district_id']) return false;
        if ($task['upazila_id'] !== null && $actor['upazila_id'] !== null && (int) $task['upazila_id'] !== (int) $actor['upazila_id']) return false;
        return true;
    }

    /** @return array{task:array,created:array[],effects:string[]} */
    public function act(int $taskId, string $action, array $data, array $actor): array
    {
        $task = $this->store->getTask($taskId);
        if ($task['status'] !== 'pending') throw new WorkflowException('এই পদক্ষেপ ইতিমধ্যে সম্পন্ন হয়েছে');
        if (! $this->canAct($actor, $task)) throw new WorkflowException('এই পদক্ষেপে কাজ করার অনুমতি আপনার নেই');
        $def = $this->definition($task['step']);
        $act = $def['actions'][$action] ?? throw new WorkflowException("অজানা কার্যক্রম: $action");
        $ctx = $this->store->appContext($task['app_id']);

        $this->validate(array_merge($def['fields'] ?? [], $act['fields'] ?? []), $data, $task);
        foreach ($act['guards'] ?? [] as $g) {
            if ($this->guards && ($err = ($this->guards)($g, $task, $data, $ctx))) throw new WorkflowException($err, [$g]);
        }

        $this->store->completeTask($taskId, ['action' => $action, 'data' => $data, 'actor_id' => $actor['id']]);
        $effects = [];
        foreach ($act['effects'] ?? [] as $e) {
            if ($this->effects) ($this->effects)($e, $task, $data, $actor, $ctx);
            $effects[] = $e;
        }
        $ctx = $this->store->appContext($task['app_id']);   // effects may change the context

        $created = [];
        foreach ($act['next'] ?? [] as $spec) {
            foreach ($this->plan($spec, $task, $data, $ctx) as $new) $created[] = $this->store->createTask($new);
        }
        $this->store->log(['app_id' => $task['app_id'], 'task_id' => $taskId, 'step' => $task['step'], 'action' => $action,
            'actor_id' => $actor['id'], 'actor_role' => $actor['role'], 'comment' => $data['comment'] ?? null, 'data' => $data]);
        return ['task' => $this->store->getTask($taskId), 'created' => $created, 'effects' => $effects];
    }

    private function validate(array $fields, array $data, array $task): void
    {
        $errors = [];
        foreach ($fields as $f) {
            $v = $data[$f['name']] ?? null;
            $empty = $v === null || $v === '' || $v === [];
            if ($f['required'] && $empty) { $errors[$f['name']] = ($f['label'] ?? $f['name']) . ' আবশ্যক'; continue; }
            if ($empty) continue;
            if ($f['type'] === 'select' && ! array_key_exists($v, $f['options'] ?? [])) $errors[$f['name']] = 'অবৈধ মান';
            if ($f['type'] === 'number' && ! is_numeric($v)) $errors[$f['name']] = 'সংখ্যা দিন';
            if (in_array($f['type'], ['user', 'users'], true)) {
                foreach ((array) $v as $uid) {
                    $u = $this->store->userInfo((int) $uid);
                    if (! $u || $u['role'] !== $f['role']) { $errors[$f['name']] = 'নির্বাচিত ব্যবহারকারী সঠিক ভূমিকার নন'; break; }
                    if ($task['upazila_id'] !== null && $u['upazila_id'] !== null && (int) $u['upazila_id'] !== (int) $task['upazila_id']) {
                        $errors[$f['name']] = 'নির্বাচিত ব্যবহারকারী ভিন্ন উপজেলার'; break;
                    }
                }
            }
        }
        if ($errors) throw new WorkflowException('ফর্মে ত্রুটি আছে', $errors);
    }

    private function ph(array $names, ?int $upazila): array
    {
        return array_map(fn ($g) => str_replace('{u}', (string) $upazila, $g), $names);
    }

    /** @return array[] task specs to create for one "next" entry */
    private function plan(array $spec, array $task, array $data, array $ctx): array
    {
        if (isset($spec['when']) && ($data[$spec['when'][0]] ?? null) !== $spec['when'][1]) return [];
        $inherit = ! empty($spec['inherit_upazila']);
        $groups = $task['groups'];

        if (! empty($spec['join'])) {
            $wait = $this->ph($spec['join'], $task['upazila_id']);
            foreach ($this->store->pendingTasks($task['app_id']) as $t) {
                if ($t['id'] !== $task['id'] && array_intersect($wait, $t['groups'])) return [];     // branch still running
            }
        }
        $groups = array_values(array_diff($groups, $this->ph($spec['drop_groups'] ?? [], $task['upazila_id'])));

        $targets = [];   // each: [user_id, upazila_id]
        if (($spec['per'] ?? null) === 'upazila') {
            foreach ($ctx['upazila_ids'] as $u) $targets[] = [null, (int) $u];
        } elseif (isset($spec['per']) && str_starts_with($spec['per'], 'field:')) {
            foreach ((array) ($data[substr($spec['per'], 6)] ?? []) as $u) $targets[] = [null, (int) $u];
        } elseif (isset($spec['assign_many'])) {
            foreach ((array) ($data[$spec['assign_many']] ?? []) as $uid) {
                $u = $this->store->userInfo((int) $uid);
                $targets[] = [(int) $uid, $inherit ? $task['upazila_id'] : ($u['upazila_id'] ?? null)];
            }
        } elseif (isset($spec['assign'])) {
            $uid = (int) ($data[$spec['assign']] ?? 0);
            $u = $this->store->userInfo($uid);
            $targets[] = [$uid, $inherit ? $task['upazila_id'] : ($u['upazila_id'] ?? null)];
        } elseif (! empty($spec['assign_parent_actor'])) {
            $parent = $task['parent_task_id'] ? $this->store->getTask($task['parent_task_id']) : null;
            $targets[] = [$parent['completed_by'] ?? null, $inherit ? $task['upazila_id'] : null];
        } elseif (! empty($spec['assign_creator'])) {
            $targets[] = [$ctx['creator_id'], null];
        } else {
            $targets[] = [null, $inherit ? $task['upazila_id'] : null];
        }

        $def = $this->definition($spec['step']);
        $existing = $this->store->pendingTasks($task['app_id']);
        $out = [];
        foreach ($targets as [$uid, $up]) {
            foreach ($existing as $t) {      // never create the same pending task twice
                if ($t['step'] === $spec['step'] && (int) $t['upazila_id'] === (int) $up && (int) $t['user_id'] === (int) $uid) continue 2;
            }
            $g = array_values(array_unique(array_merge($groups, $this->ph($spec['add_groups'] ?? [], $up ?? $task['upazila_id']))));
            $out[] = ['app_id' => $task['app_id'], 'step' => $spec['step'], 'role' => $def['role'], 'user_id' => $uid, 'district_id' => $ctx['district_id'],
                'upazila_id' => $up, 'groups' => $g, 'parent_task_id' => $task['id'], 'record_id' => $task['record_id'], 'sla' => $def['sla'] ?? null];
        }
        return $out;
    }
}
