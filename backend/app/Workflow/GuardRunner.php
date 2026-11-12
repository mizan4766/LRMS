<?php

namespace App\Workflow;

/** Guards that depend only on the task graph; domain guards are delegated to $domain (callable). */
class GuardRunner
{
    /** pending-step groups used by the "no_pending:X" guards */
    public const PENDING = [
        'claims' => ['S18', 'S19'],
        'objections' => ['O02A', 'O02B', 'O02C', 'O02D'],
        'compensation' => ['S26', 'S27'],
    ];

    /** @param callable|null $domain fn(string $name, array $task, array $data): ?string */
    public function __construct(private Store $store, private $domain = null)
    {
    }

    public function __invoke(string $guard, array $task, array $data, array $ctx): ?string
    {
        if (str_starts_with($guard, 'no_pending:')) {
            $key = substr($guard, 11);
            $steps = self::PENDING[$key] ?? [];
            foreach ($this->store->pendingTasks($task['app_id']) as $t) {
                if ($t['id'] !== $task['id'] && in_array($t['step'], $steps, true)) {
                    return match ($key) {
                        'claims' => 'মালিকানা দাবি যাচাই এখনো অসম্পূর্ণ',
                        'objections' => 'আপত্তির নিষ্পত্তি এখনো অসম্পূর্ণ',
                        default => 'ক্ষতিপূরণ আবেদন যাচাই/বিতরণ এখনো অসম্পূর্ণ',
                    };
                }
            }
            return null;
        }
        return $this->domain ? ($this->domain)($guard, $task, $data) : null;
    }
}
