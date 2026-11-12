<?php

namespace App\Workflow;

use App\Models\Application;
use App\Models\ApplicationLand;
use App\Models\Payment;
use App\Models\Record;
use App\Models\Task;
use App\Services\CompensationService;
use App\Services\DeadlineService;
use App\Services\NoticeTemplates;
use App\Services\NumberService;
use App\Services\Payments\Gateways;

/** Domain side effects named in config/steps.php. */
class EffectExecutor
{
    public function __construct(private NumberService $numbers, private CompensationService $comp, private DeadlineService $deadlines)
    {
    }

    public static function noticeContext(Application $app, array $extra = []): array
    {
        $lands = $app->lands()->with(['upazila', 'mouza'])->get()->map(fn ($l) => ['upazila' => $l->upazila?->name_bn, 'mouza' => $l->mouza?->name_bn, 'jl' => $l->jl_no,
            'khatian' => $l->khatian_no, 'dag' => $l->dag_no, 'area' => $l->proposed_area_acre])->all();
        return $extra + ['la_case_no' => $app->la_case_no ?? '', 'project' => $app->project_name_bn, 'body' => $app->body?->name_bn, 'district' => $app->district?->name_bn,
            'date' => now()->format('d/m/Y'), 'lands' => $lands];
    }

    public function __invoke(string $effect, array $task, array $data, array $actor, array $ctx): void
    {
        [$name, $arg] = array_pad(explode(':', $effect, 2), 2, null);
        $app = Application::findOrFail($task['app_id']);
        $rec = fn (string $kind, ?string $status = null) => Record::where('application_id', $app->id)->where('kind', $kind)->when($status, fn ($q) => $q->where('status', $status))->latest('id')->first();

        switch ($name) {
            case 'save_record':
                Record::create(['application_id' => $app->id, 'kind' => $arg, 'status' => 'saved', 'data' => $data, 'created_by' => $actor['id']]);
                break;
            case 'return_to_rb':
                $app->update(['status' => 'needs_refinement', 'refine_note' => $data['refine_note'] ?? null]);
                break;
            case 'resubmit_refined':
                $app->update(['status' => 'under_verification', 'version' => $app->version + 1]);
                break;
            case 'close_application':
                $app->update(['status' => $arg ?: 'rejected']);
                Task::where('application_id', $app->id)->where('status', 'pending')->where('id', '!=', $task['id'])->update(['status' => 'cancelled']);
                break;
            case 'assign_la_case_no':
                if (! $app->la_case_no) $app->update(['la_case_no' => $this->numbers->laCaseNo($app->district?->short_code ?? 'XXX')]);
                break;
            case 'draft_notice':
                $app->refresh();
                $no = $this->numbers->noticeNo($arg, $app->la_case_no ?: ('APP-' . $app->id));
                Record::create(['application_id' => $app->id, 'kind' => 'notice_' . $arg, 'ref_no' => $no, 'status' => 'draft', 'created_by' => $actor['id'],
                    'data' => ['html' => $data['notice_body'] ?? '', 'distribution_date' => $data['distribution_date'] ?? null, 'place' => $data['place'] ?? null]]);
                break;
            case 'approve_notice':
                if ($r = $rec('notice_' . $arg, 'draft')) $r->update(['status' => 'published', 'approved_by' => $actor['id'], 'published_at' => now()]);
                if ($arg === '4_1') $app->update(['objection_due_at' => $this->deadlines->due(now(), (int) config('larms.objection_days')), 'status' => 'processing']);
                if ($arg === '7_1') $app->update(['claims_open' => true]);
                if ($arg === '8_3') $app->update(['compensation_open' => true]);
                break;
            case 'save_field_book':
                $r = $rec('field_book') ?: new Record(['application_id' => $app->id, 'kind' => 'field_book', 'created_by' => $actor['id']]);
                $r->fill(['status' => 'saved', 'data' => ['entries' => $data['field_book'] ?? [], 'class_change_needed' => $data['class_change_needed'] ?? 'no', 'comment' => $data['comment'] ?? null]])->save();
                break;
            case 'approve_field_book':
                if ($r = $rec('field_book')) $r->update(['status' => 'approved', 'approved_by' => $actor['id']]);
                break;
            case 'apply_class_changes':
                foreach ($data['class_changes'] ?? [] as $c) {
                    if (! empty($c['land_id']) && ! empty($c['new_class_id'])) ApplicationLand::where('application_id', $app->id)->whereKey($c['land_id'])->update(['usage_class_id' => $c['new_class_id']]);
                }
                Record::create(['application_id' => $app->id, 'kind' => 'class_change_report', 'status' => 'saved', 'data' => $data, 'created_by' => $actor['id']]);
                break;
            case 'claim_verified': $this->setRecord($task, 'verified', $data); break;
            case 'claim_rejected': $this->setRecord($task, 'rejected', $data); break;
            case 'claim_sent_to_dc': $this->setRecord($task, 'sent_to_dc', $data); break;
            case 'claim_field_book_updated': $this->setRecord($task, 'field_book_updated', $data); break;
            case 'comp_app_approved': $this->setRecord($task, 'approved', $data); break;
            case 'comp_app_rejected': $this->setRecord($task, 'rejected', $data); break;
            case 'save_roydad':
                $est = $this->comp->estimate($data['estimate']['lines'] ?? [], $app->body_type, (float) ($data['estimate']['tax_percent'] ?? config('larms.tax_at_source_percent')));
                Record::create(['application_id' => $app->id, 'kind' => 'roydad', 'status' => 'saved', 'created_by' => $actor['id'], 'ref_no' => 'RD-' . ($app->la_case_no ?: $app->id), 'data' => $est]);
                break;
            case 'approve_roydad':
                if ($r = $rec('roydad', 'saved')) {
                    $r->update(['status' => 'approved', 'approved_by' => $actor['id'], 'published_at' => now()]);
                    $app->update(['compensation_total' => $r->data['gross_total'] ?? 0, 'deposit_required' => $r->data['estimate_total'] ?? 0]);
                }
                break;
            case 'create_deposit_demand':
                Record::create(['application_id' => $app->id, 'kind' => 'deposit_demand', 'status' => 'issued', 'created_by' => $actor['id'], 'published_at' => now(),
                    'data' => $data + ['amount' => (float) $app->deposit_required]]);
                break;
            case 'record_deposit':
                $g = Gateways::make()->recordDeposit(['reference' => $data['reference_no'], 'method' => $data['method'], 'date' => $data['paid_date'], 'amount' => $data['paid_amount']]);
                Payment::create(['application_id' => $app->id, 'kind' => 'deposit', 'payee_name' => $app->body?->name_bn, 'amount' => $data['paid_amount'], 'method' => $g['method'],
                    'reference' => $g['reference'], 'status' => $g['status'], 'paid_at' => $g['paid_at'], 'created_by' => $actor['id']]);
                break;
            case 'verify_deposit':
                Payment::where('application_id', $app->id)->where('kind', 'deposit')->where('status', 'received')->update(['status' => 'verified']);
                break;
            case 'reject_deposit':
                Payment::where('application_id', $app->id)->where('kind', 'deposit')->where('status', 'received')->update(['status' => 'rejected']);
                break;
            case 'distribute_payment':
                $r = $task['record_id'] ? Record::find($task['record_id']) : null;
                $amount = (float) ($r?->data['entitled_amount'] ?? 0);
                $g = Gateways::make()->disburse(['reference' => $data['reference'], 'method' => $data['method'], 'amount' => $amount]);
                Payment::create(['application_id' => $app->id, 'kind' => 'distribution', 'record_id' => $r?->id, 'payee_name' => $r?->data['name'] ?? null, 'amount' => $amount,
                    'method' => $g['method'], 'reference' => $g['reference'], 'status' => $g['status'], 'paid_at' => $g['paid_at'], 'created_by' => $actor['id']]);
                $this->setRecord($task, 'paid', $data);
                break;
            case 'approve_form16':
                Record::create(['application_id' => $app->id, 'kind' => 'form16', 'status' => 'approved', 'approved_by' => $actor['id'], 'published_at' => now(), 'data' => $data]);
                break;
            case 'complete_application':
                $app->update(['status' => 'completed', 'phase' => 'সম্পন্ন']);
                break;
            case 'objection_hearing_set': $this->setRecord($task, 'hearing_set', $data); break;
            case 'objection_report': $this->setRecord($task, 'reported', $data); break;
            case 'objection_primary_decision': $this->setRecord($task, 'primary_decided', $data); break;
            case 'objection_final_decision': $this->setRecord($task, 'closed', $data); break;
            default:
                throw new WorkflowException("অজানা effect: $effect");
        }
    }

    private function setRecord(array $task, string $status, array $data): void
    {
        if (! $task['record_id'] || ! ($r = Record::find($task['record_id']))) return;
        $d = $r->data ?? [];
        $d['steps'][$task['step']] = $data;
        $r->update(['status' => $status, 'data' => $d]);
    }
}
