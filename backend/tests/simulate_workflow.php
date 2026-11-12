<?php
/**
 * Plain-PHP simulation of the whole LARMS workflow (no Laravel needed):  php tests/simulate_workflow.php
 * Walks the script "LARMS software workflow" from Step 02 to Step 31 plus ownership claims, compensation
 * applications and the objection flow, and checks routing, joins, guards and permissions.
 */
require __DIR__ . '/../app/Workflow/WorkflowException.php';
require __DIR__ . '/../app/Workflow/Store.php';
require __DIR__ . '/../app/Workflow/InMemoryStore.php';
require __DIR__ . '/../app/Workflow/GuardRunner.php';
require __DIR__ . '/../app/Workflow/WorkflowEngine.php';

use App\Workflow\{InMemoryStore, GuardRunner, WorkflowEngine, WorkflowException};

$steps = require __DIR__ . '/../config/steps.php';
$store = new InMemoryStore();
$D = 1;
$u = fn (int $id, string $role, ?int $up = null) => $store->addUser(['id' => $id, 'role' => $role, 'district_id' => $D, 'upazila_id' => $up]);
$u(1, 'rb'); $u(2, 'dc'); $u(3, 'adc'); $u(4, 'lao'); $u(13, 'kanungo'); $u(14, 'surveyor'); $u(15, 'commissioner'); $u(16, 'citizen'); $u(17, 'accounts');
foreach ([101 => [5, 9], 102 => [6, 10]] as $up => [$ac, $ul]) { $u($ac, 'ac_land', $up); $u($ul, 'ulao', $up); }
$u(110, 'ulao', 102);                 // a second ULAO in upazila 102
$u(7, 'ac_land', 103);                // AC Land of an unrelated upazila
$store->addApp(1, ['district_id' => $D, 'upazila_ids' => [101, 102], 'is_npp' => false, 'creator_id' => 1]);

$domainGuards = fn ($g, $t, $d) => null;
$engine = new WorkflowEngine($steps, $store, new GuardRunner($store, $domainGuards), function ($e, $t, $d, $a, $c) use (&$effects) { $effects[] = $e; });
$effects = [];
$pass = 0; $fail = 0;
$ok = function (string $name, bool $cond) use (&$pass, &$fail) { echo ($cond ? '  ok   ' : '  FAIL ') . $name . "\n"; $cond ? $pass++ : $fail++; };
$pending = fn (string $step = null) => array_values(array_filter($store->pendingTasks(1), fn ($t) => $step === null || $t['step'] === $step));
$fill = function (string $step, string $action, array $data) use ($steps) {   // UI defaults for required fields not given by the scenario
    $fields = array_merge($steps[$step]['fields'] ?? [], $steps[$step]['actions'][$action]['fields'] ?? []);
    foreach ($fields as $f) {
        if (! empty($data[$f['name']]) || ! $f['required'] || in_array($f['type'], ['user', 'users', 'upazilas'], true)) continue;
        $data[$f['name']] = ($f['default'] ?? '') !== '' ? $f['default'] : match ($f['type']) { 'select' => array_key_first($f['options']), 'number' => 1000, 'date' => '2026-11-01', 'time' => '10:00', 'component' => [['x' => 1]], default => 'x' };
    }
    return $data;
};
$act = function (int $uid, string $step, string $action, array $data = [], ?int $upazila = null) use ($engine, $store, $pending, $fill) {
    $data = $fill($step, $action, $data);
    foreach ($pending($step) as $t) {
        if ($upazila !== null && (int) $t['upazila_id'] !== $upazila) continue;
        if ($t['user_id'] !== null && (int) $t['user_id'] !== $uid) continue;
        return $engine->act($t['id'], $action, $data + ['comment' => 'ok'], $store->userInfo($uid));
    }
    throw new RuntimeException("no pending $step for user $uid");
};
$throws = function (callable $fn): ?string { try { $fn(); return null; } catch (WorkflowException $e) { return $e->getMessage(); } };
$steplist = fn () => array_map(fn ($t) => $t['step'] . ($t['upazila_id'] ? ":{$t['upazila_id']}" : ''), $pending());

echo "== Step 02-03: receive and review\n";
$engine->spawn(1, 'S02');
$ok('S02 task exists for DC', count($pending('S02')) === 1);
$ok('ADC cannot act on S02 (wrong role)', $throws(fn () => $act(3, 'S02', 'forward')) !== null);
$ok('DC of another district cannot act', (function () use ($engine, $store, $pending) { $store->addUser(['id' => 99, 'role' => 'dc', 'district_id' => 2]); try { $engine->act($pending('S02')[0]['id'], 'forward', ['comment' => 'x'], $store->userInfo(99)); return false; } catch (WorkflowException $e) { return true; } })());
$act(2, 'S02', 'forward'); $act(3, 'S03', 'approve');
$ok('S03_1 (LAO) created', count($pending('S03_1')) === 1);
$ok('required field enforced (kanungo_id)', $throws(fn () => $act(4, 'S03_1', 'send', ['comment' => 'x'])) !== null);
$ok('role of selected user enforced', $throws(fn () => $act(4, 'S03_1', 'send', ['kanungo_id' => 14])) !== null);

echo "== Step 03.1: parallel branches (attachments + cha form per upazila)\n";
$act(4, 'S03_1', 'send', ['kanungo_id' => 13]);
$ok('attachment branch assigned to the selected kanungo', $pending('S05A')[0]['user_id'] === 13);
$ok('one AC Land task per upazila (2)', count($pending('S04')) === 2);
$ok('AC Land of an unrelated upazila cannot act', $store->userInfo(7) && ! $engine->canAct($store->userInfo(7), $pending('S04')[0]));

echo "== Step 04: cha form chain\n";
$ok('ULAO must belong to the same upazila', $throws(fn () => $act(5, 'S04', 'send', ['ulao_ids' => [10]], 101)) !== null);
$act(5, 'S04', 'send', ['ulao_ids' => [9]], 101);
$act(6, 'S04', 'send', ['ulao_ids' => [10, 110]], 102);
$ok('3 ULAO tasks (1 + 2)', count($pending('S04_1')) === 3);
$act(9, 'S04_1', 'send', ['kanungo_id' => 13, 'comment' => 'summary']);
$act(13, 'S04_2', 'correct', [], 101);
$ok('S04_5 created for upazila 101', count($pending('S04_5')) === 1);
$act(5, 'S04_5', 'send', [], 101);
$ok('S05 not yet created (other branches pending)', count($pending('S05')) === 0);
$act(10, 'S04_1', 'send', ['kanungo_id' => 13, 'comment' => 's']);
$act(13, 'S04_2', 'assign_surveyor', ['surveyor_id' => 14], 102);
$act(14, 'S04_3', 'send', ['comment' => 'checked']);
$ok('surveyor result returns to the kanungo who assigned', $pending('S04_4')[0]['user_id'] === 13);
$act(13, 'S04_4', 'correct', ['comment' => 'ok'], 102);
$ok('S04_5 (u102) waits for the second ULAO', count($pending('S04_5')) === 0);
$act(110, 'S04_1', 'send', ['kanungo_id' => 13, 'comment' => 's']);
$act(13, 'S04_2', 'correct', [], 102);
$ok('S04_5 created for upazila 102 after both ULAOs', count($pending('S04_5')) === 1);

echo "== Step 05: attachment chain and join\n";
$act(13, 'S05A', 'send', ['surveyor_id' => 14, 'comment' => 'assign']);
$act(14, 'S05B', 'send', ['comment' => 'files ok']);
$act(13, 'S05C', 'correct');
$ok('S05 still waits for AC Land (u102)', count($pending('S05')) === 0);
$act(6, 'S04_5', 'send', [], 102);
$ok('S05 (LAO) created when every branch finished', count($pending('S05')) === 1);

echo "== Step 5.2-5.6: refinement loop\n";
$ok('refine requires a note', $throws(fn () => $engine->act($pending('S05')[0]['id'], 'refine', ['comment' => 'c', 'refine_note' => ''], $store->userInfo(4))) !== null);
$act(4, 'S05', 'refine', ['refine_note' => 'সংশোধন প্রয়োজন']);
$ok('S05R goes back to the applicant (RB)', $pending('S05R')[0]['user_id'] === 1);
$act(1, 'S05R', 'resubmit');
$act(4, 'S05_4', 'send', ['upazila_ids' => [102]]);
$ok('only the affected upazila is re-verified', count($pending('S04R')) === 1 && $pending('S04R')[0]['upazila_id'] === 102);
$act(6, 'S04R', 'correct');
$ok('LAO gets S05 again', count($pending('S05')) === 1);
$act(4, 'S05', 'ok');

echo "== Step 6-8: decision, feasibility, meeting\n";
$act(2, 'S06', 'approve');
$act(3, 'S07_1', 'save', ['members' => [['name' => 'ক', 'designation' => 'খ']], 'meeting_date' => '2026-11-01', 'meeting_time' => '10:00', 'place' => 'ডিসি অফিস']);
$act(3, 'S07_2', 'submit', ['details' => 'd', 'items' => [['no' => 3, 'opinion' => 'x']], 'summary' => 's']);
$act(2, 'S08_1', 'approve', ['committee' => 'district']);
$act(2, 'S08_2', 'save', ['subject' => 'সভা', 'meeting_date' => '2026-11-05', 'meeting_time' => '11:00', 'place' => 'সভাকক্ষ']);
$act(2, 'S08_3', 'save', ['discussion' => 'আলোচনা']);
$act(2, 'S08_4', 'approve');

echo "== Step 9-12: media, sec 4(1) notice, joint inquiry committee\n";
$act(4, 'S09', 'submit', ['media' => [['land_id' => 1]]]);
$act(2, 'S10', 'approve'); $act(4, 'S11', 'submit', ['notice_body' => '<p>notice</p>']);
$act(2, 'S12_1', 'approve');
$ok('effects ran: assign_la_case_no, draft/approve notice 4_1', in_array('assign_la_case_no', $effects) && in_array('draft_notice:4_1', $effects) && in_array('approve_notice:4_1', $effects));
$act(2, 'S12_2', 'save', ['members' => [['name' => 'ক']], 'meeting_date' => '2026-11-10', 'meeting_time' => '10:00', 'place' => 'স্থল']);

echo "== Step 13: field book with class change\n";
$act(3, 'S13', 'forward', ['field_book' => [['land_id' => 1]], 'class_change_needed' => 'yes']);
$ok('class change goes to the misc case, not to the DC', count($pending('S13_1')) === 1 && count($pending('S14')) === 0);
$act(3, 'S13_1', 'forward'); $act(4, 'S13_2', 'save', ['class_changes' => [['land_id' => 1, 'new_class' => 'x']]]); $act(3, 'S13_3', 'forward');
$act(2, 'S14', 'approve');
$act(4, 'S15', 'submit', ['notice_body' => '<p>7(1)</p>']);
$act(2, 'S16', 'approve');
$ok('S20 (price letter) opened after the 7(1) notice', count($pending('S20')) === 1);

echo "== Step 17-19: ownership claim blocks the price letter until resolved\n";
$claim = $engine->spawn(1, 'S18', ['record_id' => 7]);
$ok('S20 blocked while a claim is pending', $throws(fn () => $act(4, 'S20', 'send', ['subject' => 's', 'desc_sub_registrar' => 'a', 'desc_officers' => 'b'])) !== null);
$act(4, 'S18', 'send_to_dc'); $act(2, 'S19', 'approve', ['field_book_update' => 'সংশোধিত']);
$act(4, 'S20', 'send', ['subject' => 's', 'desc_sub_registrar' => 'a', 'desc_officers' => 'b']);

echo "== Step 20-22: valuation, estimate, roydad, deposit\n";
$act(4, 'S20_1', 'save', ['valuation' => [['rate' => 1]]]);
$act(4, 'S20_2', 'submit', ['estimate' => ['lines' => []]]);
$act(2, 'S21', 'approve'); $act(2, 'S21_2', 'send', ['subject' => 's', 'details' => 'd']);
$ok('deposit task assigned to the applicant (RB)', $pending('S22')[0]['user_id'] === 1);
$act(1, 'S22', 'pay', ['method' => 'challan', 'reference_no' => 'CH-1', 'paid_amount' => 1000, 'paid_date' => '2026-12-01']);

echo "== Step 22.1: the accounts officer verifies the deposit\n";
$ok('S22V waits for the accounts officer', count($pending('S22V')) === 1 && $pending('S22V')[0]['role'] === 'accounts');
$ok('S23 is not open before the deposit is verified', count($pending('S23')) === 0);
$ok('LAO cannot verify the deposit (wrong role)', $throws(fn () => $act(4, 'S22V', 'verify')) !== null);
$act(17, 'S22V', 'reject');
$ok('rejected deposit goes back to the applicant (RB)', count($pending('S22')) === 1 && $pending('S22')[0]['user_id'] === 1);
$act(1, 'S22', 'pay', ['method' => 'challan', 'reference_no' => 'CH-2', 'paid_amount' => 1000, 'paid_date' => '2026-12-02']);
$act(17, 'S22V', 'verify');
$ok('S23 opens after the deposit is verified', count($pending('S23')) === 1);

echo "== Objection flow blocks the 8(3) notice\n";
$engine->spawn(1, 'O02A', ['record_id' => 3]);
$ok('S23 blocked while an objection is pending', $throws(fn () => $act(4, 'S23', 'submit', ['distribution_date' => '2027-01-10', 'place' => 'x', 'notice_body' => 'n'])) !== null);
$act(2, 'O02A', 'save', ['hearing_date' => '2026-12-05', 'hearing_time' => '10:00']);
$act(2, 'O02B', 'forward', ['decision_text' => 't', 'forward_to' => 'commissioner']);
$ok('commissioner decides next', $throws(fn () => $act(2, 'O02C', 'submit', ['decision_text' => 'x'])) !== null);
$act(15, 'O02C', 'submit', ['decision_text' => 'প্রাথমিক সিদ্ধান্ত']);
$act(2, 'O02D', 'submit', ['decision_text' => 'চূড়ান্ত']);

echo "== Step 23-27: 8(3) notice, compensation applications, distribution\n";
$act(4, 'S23', 'submit', ['distribution_date' => '2027-01-10', 'place' => 'x', 'notice_body' => 'n']);
$act(2, 'S24', 'publish');
$engine->spawn(1, 'S26', ['record_id' => 11]);
$ok('S28 blocked while a compensation application is pending', $throws(fn () => $act(4, 'S28', 'send', ['handover_place' => 'p', 'handover_date' => '2027-02-01', 'handover_time' => '10:00', 'agreement_note' => 'n'])) !== null);
$act(4, 'S26', 'approve'); $act(2, 'S27', 'pay', ['method' => 'mobile_banking', 'reference' => 'TX1']);

echo "== Step 28-31: possession, declaration, gazette, deed\n";
$act(4, 'S28', 'send', ['handover_place' => 'p', 'handover_date' => '2027-02-01', 'handover_time' => '10:00', 'agreement_note' => 'n']);
$act(2, 'S29', 'approve'); $act(4, 'S30', 'submit', ['declaration' => 'ঘোষণা']);
$act(2, 'S31A', 'approve'); $act(2, 'S31B', 'publish', ['gazette_no' => 'G-1', 'gazette_date' => '2027-03-01']);
$act(2, 'S31C', 'approve', ['deed_no' => 'D-1', 'deed_date' => '2027-03-10']);
$ok('application completed (effect) and no pending tasks remain', in_array('complete_application', $effects) && count($pending()) === 0);
$ok('history logged for every completed task', count($store->logs) === count(array_filter($store->tasks, fn ($t) => $t['status'] === 'done')));
$ok('a finished task cannot be repeated', $throws(fn () => $engine->act(1, 'forward', ['comment' => 'x'], $store->userInfo(2))) !== null);

echo "\nsteps defined: " . count($steps) . ", tasks created: " . count($store->tasks) . ", passed: $pass, failed: $fail\n";
exit($fail ? 1 : 0);
