<?php

namespace App\Workflow;

/** Persistence used by the engine. Implemented by EloquentStore (MySQL) and InMemoryStore (tests). */
interface Store
{
    public function getTask(int $id): array;

    /** @return array[] all pending tasks of the application */
    public function pendingTasks(int $appId): array;

    /** @param array $spec app_id, step, role, user_id, district_id, upazila_id, groups[], parent_task_id, record_id, sla */
    public function createTask(array $spec): array;

    /** @param array $result action, data, actor_id */
    public function completeTask(int $id, array $result): void;

    /** @return array{district_id:?int,upazila_ids:int[],is_npp:bool,creator_id:?int} */
    public function appContext(int $appId): array;

    /** @return array{id:int,role:string,district_id:?int,upazila_id:?int}|null */
    public function userInfo(int $userId): ?array;

    public function log(array $entry): void;
}
