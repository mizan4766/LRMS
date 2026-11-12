<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Runs the plain-PHP walk-through of all workflow steps (tests/simulate_workflow.php). */
class LarmsSimulate extends Command
{
    protected $signature = 'larms:simulate';
    protected $description = 'Simulate the whole workflow (Step 02 to 31, claims, compensation applications, objection) and report pass/fail';

    public function handle(): int
    {
        passthru('"' . PHP_BINARY . '" ' . escapeshellarg(base_path('tests/simulate_workflow.php')), $code);
        return $code ? self::FAILURE : self::SUCCESS;
    }
}
