<?php

namespace App\Workflow;

/** Array-backed Store used by tests and the CLI simulation. */
class InMemoryStore implements Store
{
    public array $tasks = [];
    public array $logs = [];
    public array $users = [];
    public array $apps = [];
    private int $seq = 0;

    public function addUser(array $u): void { $this->users[$u['id']] = $u + ['district_id' => null, 'upazila_id' => null]; }
    public function addApp(int $id, array $ctx): void { $this->apps[$id] = $ctx; }

    public function getTask(int $id): array { return $this->tasks[$id] ?? throw new WorkflowException("Task $id not found"); }

    public function pendingTasks(int $appId): array
    {
        return array_values(array_filter($this->tasks, fn ($t) => $t['app_id'] === $appId && $t['status'] === 'pending'));
    }

    public function createTask(array $spec): array
    {
        $id = ++$this->seq;
        return $this->tasks[$id] = $spec + ['id' => $id, 'status' => 'pending', 'completed_by' => null, 'action' => null, 'data' => null, 'record_id' => null, 'parent_task_id' => null] + ['groups' => []];
    }

    public function completeTask(int $id, array $result): void
    {
        $this->tasks[$id]['status'] = 'done';
        $this->tasks[$id]['completed_by'] = $result['actor_id'];
        $this->tasks[$id]['action'] = $result['action'];
        $this->tasks[$id]['data'] = $result['data'];
    }

    public function appContext(int $appId): array { return $this->apps[$appId]; }
    public function userInfo(int $userId): ?array { return $this->users[$userId] ?? null; }
    public function log(array $entry): void { $this->logs[] = $entry; }
}
