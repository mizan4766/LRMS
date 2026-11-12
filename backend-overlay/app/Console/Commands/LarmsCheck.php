<?php

namespace App\Console\Commands;

use App\Services\CompensationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class LarmsCheck extends Command
{
    protected $signature = 'larms:check';
    protected $description = 'Self-test: database, seed data, routes, frontend, workflow definition and calculation engine';

    public function handle(): int
    {
        $fail = 0;
        $row = function (string $name, callable $fn) use (&$fail) {
            try { $r = $fn(); $ok = $r === true || (is_array($r) && ($r[0] ?? false)); $msg = is_array($r) ? ($r[1] ?? '') : ''; } catch (\Throwable $e) { $ok = false; $msg = $e->getMessage(); }
            $this->line(($ok ? '  [ OK ] ' : '  [FAIL] ') . $name . ($msg ? "  - $msg" : '')); if (! $ok) $fail++;
        };
        $row('Database connection', fn () => [DB::connection()->getPdo() !== null, DB::connection()->getDatabaseName()]);
        $row('Tables created (>= 15)', function () { $n = count(DB::select('SHOW TABLES')); return [$n >= 15, "$n tables"]; });
        $row('Users seeded (one per role)', function () { $n = DB::table('users')->distinct()->count('role'); return [$n >= 11, "$n roles in use"]; });
        $row('Sylhet district + 4 upazilas', fn () => [DB::table('upazilas')->count() >= 4, DB::table('upazilas')->count() . ' upazilas']);
        $row('Demo application with 5 lands', fn () => [DB::table('application_lands')->count() >= 5, DB::table('application_lands')->count() . ' lands']);
        $row('Workflow steps defined (>= 55)', fn () => [count(config('steps')) >= 55, count(config('steps')) . ' steps']);
        $row('Attachment lists (25 govt + 25 private)', fn () => [count(config('attachments.govt')) === 25 && count(config('attachments.private')) === 25, '']);
        $row('API routes registered (>= 30)', fn () => [count(Route::getRoutes()->getRoutes()) >= 30, count(Route::getRoutes()->getRoutes()) . ' routes']);
        $row('Frontend installed (public/app/index.html)', fn () => is_file(public_path('app/index.html')));
        $row('Storage writable', fn () => is_writable(storage_path('app')) && is_writable(storage_path('logs')));
        $c = new CompensationService();
        $row('Govt compensation: land x 3 (200% premium) + other items x 2 (100% extra)', fn () => $c->line(['area_acre' => 1, 'rate_per_acre' => 1000, 'others' => ['structures' => 100]], 'govt')['gross'] === 3200.0);
        $row('Private body: land x 4 (300% premium)', fn () => $c->line(['area_acre' => 1, 'rate_per_acre' => 1000], 'private')['gross'] === 4000.0);
        $this->newLine();
        $this->line($fail === 0 ? 'ALL CHECKS PASSED  (next: php artisan larms:simulate)' : "$fail CHECK(S) FAILED - send this screen to support");
        return $fail ? self::FAILURE : self::SUCCESS;
    }
}
