<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\Record;
use App\Workflow\WorkflowException;
use App\Workflow\WorkflowService;
use Illuminate\Http\Request;

/** Interested persons (citizens): read published notices, file ownership claims, compensation applications and objections. */
class CitizenController extends Controller
{
    private function citizen(Request $r): void
    {
        abort_unless($r->user()->role === 'citizen', 403, 'শুধু নাগরিক/স্বার্থসংশ্লিষ্ট ব্যক্তি');
    }

    public function notices(Request $r)
    {
        $rows = Record::whereIn('kind', ['notice_4_1', 'notice_7_1', 'notice_8_3'])->where('status', 'published')->orderByDesc('published_at')->limit(200)->get();
        $apps = Application::with('district:id,name_bn')->whereIn('id', $rows->pluck('application_id'))->get()->keyBy('id');
        return $rows->map(fn ($n) => ['id' => $n->id, 'kind' => $n->kind, 'ref_no' => $n->ref_no, 'published_at' => $n->published_at,
            'application' => ['id' => $n->application_id, 'la_case_no' => $apps[$n->application_id]->la_case_no, 'project_name_bn' => $apps[$n->application_id]->project_name_bn,
                'district' => $apps[$n->application_id]->district?->name_bn, 'claims_open' => $apps[$n->application_id]->claims_open, 'compensation_open' => $apps[$n->application_id]->compensation_open,
                'objection_due_at' => $apps[$n->application_id]->objection_due_at, 'status' => $apps[$n->application_id]->status,
                'lands' => $apps[$n->application_id]->claims_open ? $apps[$n->application_id]->lands()->with(['upazila:id,name_bn', 'mouza:id,name_bn'])->get()
                    ->map(fn ($l) => ['id' => $l->id, 'label' => "{$l->upazila?->name_bn} / {$l->mouza?->name_bn} — খতিয়ান {$l->khatian_no}, দাগ {$l->dag_no}"])->values() : []]])->values();
    }

    public function mine(Request $r)
    {
        $this->citizen($r);
        return Record::whereIn('kind', ['claim', 'comp_app', 'objection'])->where('created_by', $r->user()->id)->orderByDesc('id')->get();
    }

    private function attach(Request $r, Application $a, Record $rec): void
    {
        foreach ((array) $r->file('files', []) as $f) {
            $ext = strtolower($f->getClientOriginalExtension());
            if (! in_array($ext, config('attachments.allowed'), true) || $f->getSize() > config('attachments.max_file_mb') * 1048576) continue;
            Attachment::create(['application_id' => $a->id, 'kind' => 'record', 'ref_id' => $rec->id, 'title' => $f->getClientOriginalName(), 'path' => $f->store('applications/' . $a->id . '/citizen', 'local'),
                'original_name' => $f->getClientOriginalName(), 'mime' => $f->getClientMimeType(), 'size' => $f->getSize(), 'uploaded_by' => $r->user()->id]);
        }
    }

    /** Step 17: statement of the nature of interest (ownership claim) after the 7(1) notice. */
    public function claim(Request $r, int $id, WorkflowService $wf)
    {
        $this->citizen($r); $a = Application::findOrFail($id);
        if (! $a->claims_open) throw new WorkflowException('এই মামলায় মালিকানা দাবির সময় চালু নেই (৭(১) নোটিশ প্রকাশিত হয়নি)');
        $d = $r->validate(['land_ids' => 'required|array|min:1', 'interest_type' => 'required|in:owner,possessor,bargadar,mortgagee,heir,other', 'description' => 'required|string', 'claimed_share' => 'nullable|string|max:100']);
        $rec = Record::create(['application_id' => $a->id, 'kind' => 'claim', 'status' => 'submitted', 'created_by' => $r->user()->id, 'data' => $d + ['name' => $r->user()->name_bn, 'nid' => $r->user()->nid, 'mobile' => $r->user()->mobile]]);
        $this->attach($r, $a, $rec);
        $wf->spawn($a->id, 'S18', ['record_id' => $rec->id]);
        return response()->json($rec, 201);
    }

    /** Step 25: application to receive the compensation (entitlement is taken from the approved roydad by NID). */
    public function compensation(Request $r, int $id, WorkflowService $wf)
    {
        $this->citizen($r); $a = Application::findOrFail($id);
        if (! $a->compensation_open) throw new WorkflowException('ক্ষতিপূরণ আবেদনের সময় এখনো চালু হয়নি (৮(৩) নোটিশ প্রকাশিত হয়নি)');
        $d = $r->validate(['subject' => 'required|string|max:300', 'details' => 'required|string', 'account_type' => 'required|in:mobile_banking,bank', 'account_no' => 'required|string|max:60']);
        $roydad = Record::where('application_id', $a->id)->where('kind', 'roydad')->where('status', 'approved')->latest('id')->first();
        $nid = $r->user()->nid;
        $lines = collect($roydad?->data['lines'] ?? [])->filter(fn ($l) => $nid && ($l['owner_nid'] ?? null) === $nid);
        $rec = Record::create(['application_id' => $a->id, 'kind' => 'comp_app', 'status' => 'submitted', 'created_by' => $r->user()->id,
            'data' => $d + ['name' => $r->user()->name_bn, 'nid' => $nid, 'entitled_amount' => round((float) $lines->sum('net'), 2), 'matched_lines' => $lines->count()]]);
        $this->attach($r, $a, $rec);
        $wf->spawn($a->id, 'S26', ['record_id' => $rec->id]);
        return response()->json($rec, 201);
    }

    /** Objection against the notice (before the 8(3) notice). */
    public function objection(Request $r, int $id, WorkflowService $wf)
    {
        $this->citizen($r); $a = Application::findOrFail($id);
        if (! Record::where('application_id', $a->id)->whereIn('kind', ['notice_4_1', 'notice_7_1'])->where('status', 'published')->exists() || in_array($a->status, ['completed', 'rejected'], true)) {
            throw new WorkflowException('আপত্তি দাখিলের জন্য প্রকাশিত নোটিশ নেই');
        }
        $d = $r->validate(['subject' => 'required|string|max:300', 'details' => 'required|string']);
        $rec = Record::create(['application_id' => $a->id, 'kind' => 'objection', 'status' => 'filed', 'created_by' => $r->user()->id, 'data' => $d + ['name' => $r->user()->name_bn, 'nid' => $r->user()->nid]]);
        $this->attach($r, $a, $rec);
        $wf->spawn($a->id, 'O02A', ['record_id' => $rec->id]);
        return response()->json($rec, 201);
    }
}
