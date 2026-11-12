<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationLand;
use App\Models\Attachment;
use App\Models\District;
use App\Models\LandOwner;
use App\Models\Record;
use App\Models\RequiringBody;
use App\Models\Task;
use App\Models\TaskLog;
use App\Models\Upazila;
use App\Models\User;
use App\Services\AttachmentRules;
use App\Workflow\WorkflowException;
use App\Workflow\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public static function visible(User $u)
    {
        $q = Application::query();
        switch ($u->role) {
            case 'admin': case 'ministry': return $q->where('status', '!=', 'draft');
            case 'rb': return $q->where('requiring_body_id', $u->requiring_body_id);
            case 'dc': case 'adc': case 'lao': case 'accounts': return $q->where('district_id', $u->district_id)->where('status', '!=', 'draft');
            case 'commissioner': return $q->where('division_id', $u->division_id)->where('status', '!=', 'draft');
            case 'ac_land': case 'ulao': case 'kanungo': case 'surveyor':
                return $q->where('status', '!=', 'draft')->whereIn('id', Task::where(fn ($t) => $t->where('user_id', $u->id)
                    ->orWhere(fn ($x) => $x->where('role', $u->role)->whereNull('user_id')->where('upazila_id', $u->upazila_id)))->select('application_id'));
        }
        return $q->whereRaw('1 = 0');
    }

    public static function find(Request $r, int $id): Application
    {
        return self::visible($r->user())->findOrFail($id);
    }

    private function editable(Request $r, Application $a): void
    {
        abort_unless($r->user()->role === 'rb' && $a->requiring_body_id === $r->user()->requiring_body_id && in_array($a->status, ['draft', 'needs_refinement'], true), 403, 'আবেদনটি এখন সম্পাদনাযোগ্য নয়');
    }

    public function index(Request $r)
    {
        $q = self::visible($r->user())->with(['body:id,name_bn', 'district:id,name_bn'])->orderByDesc('id');
        if ($r->filled('status')) $q->where('status', $r->query('status'));
        if ($r->filled('q')) $q->where(fn ($x) => $x->where('tracking_no', 'like', '%' . $r->query('q') . '%')->orWhere('project_name_bn', 'like', '%' . $r->query('q') . '%')->orWhere('la_case_no', 'like', '%' . $r->query('q') . '%'));
        return $q->paginate(min((int) $r->query('per_page', 20), 100));
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->role === 'rb', 403, 'শুধু প্রত্যাশী সংস্থা নতুন আবেদন করতে পারে');
        $d = $r->validate(['project_name_bn' => 'required|string|max:400', 'project_name_en' => 'nullable|string|max:400', 'pd_name_bn' => 'nullable|string|max:150', 'pd_name_en' => 'nullable|string|max:150',
            'pd_designation' => 'nullable|string|max:150', 'pd_office_address' => 'nullable|string|max:400', 'pd_mobile' => 'nullable|string|max:20', 'pd_email' => 'nullable|email|max:150', 'pd_nid' => 'nullable|string|max:20']);
        $body = RequiringBody::findOrFail($r->user()->requiring_body_id);
        $a = Application::create($d + ['requiring_body_id' => $body->id, 'body_type' => $body->body_type, 'created_by' => $r->user()->id, 'status' => 'draft',
            'flags' => array_fill_keys(array_keys(config('attachments.flags')), 'no'), 'phase' => 'খসড়া']);
        return response()->json($a, 201);
    }

    public function update(Request $r, int $id)
    {
        $a = self::find($r, $id); $this->editable($r, $a);
        $d = $r->validate(['project_name_bn' => 'sometimes|string|max:400', 'project_name_en' => 'nullable|string|max:400', 'pd_name_bn' => 'nullable|string|max:150', 'pd_name_en' => 'nullable|string|max:150',
            'pd_designation' => 'nullable|string|max:150', 'pd_office_address' => 'nullable|string|max:400', 'pd_mobile' => 'nullable|string|max:20', 'pd_email' => 'nullable|email|max:150', 'pd_nid' => 'nullable|string|max:20',
            'division_id' => 'nullable|integer', 'district_id' => 'nullable|integer|exists:districts,id', 'subject' => 'nullable|string', 'has_prior_acquisition' => 'boolean', 'prior_project_name' => 'nullable|string|max:400',
            'prior_la_case_no' => 'nullable|string|max:60', 'is_npp' => 'boolean', 'flags' => 'nullable|array']);
        if (isset($d['district_id'])) $d['division_id'] = District::whereKey($d['district_id'])->value('division_id');
        if (array_key_exists('flags', $d)) {   // keep every answer, including "no" (rule 6 of the attachment spec)
            $d['flags'] = array_merge($a->flags ?? [], array_intersect_key($d['flags'], config('attachments.flags')));
            $d['flags'] = array_map(fn ($v) => $v === 'yes' ? 'yes' : 'no', $d['flags']);
        }
        if (isset($d['has_prior_acquisition']) && ! $d['has_prior_acquisition']) $d['prior_project_name'] = $d['prior_la_case_no'] = null;
        $a->update($d);
        return $a->fresh();
    }

    public function show(Request $r, int $id)
    {
        $a = self::find($r, $id);
        $cfg = config('attachments');
        $atts = Attachment::where('application_id', $a->id)->orderBy('id')->get();
        $slots = $atts->where('kind', 'proposal')->pluck('slot_no')->map(fn ($x) => (int) $x)->all();
        $checklist = array_map(fn ($it) => $it + ['uploaded' => in_array($it['no'], $slots, true)], AttachmentRules::active(AttachmentRules::listFor($cfg, $a->body_type), $a->flags ?? []));
        return ['application' => $a->load(['body:id,name_bn,name_en,body_type', 'district:id,name_bn']), 'authority_label' => $a->authority_level === 'ministry' ? 'ভূমি মন্ত্রণালয় (৫০ বিঘার ঊর্ধ্বে)' : 'বিভাগীয় কমিশনার',
            'lands' => $a->lands()->with(['owners', 'upazila:id,name_bn', 'mouza:id,name_bn,jl_no', 'recordClass:id,name_bn', 'usageClass:id,name_bn'])->get(),
            'attachments' => $atts, 'checklist' => $checklist, 'open_tasks' => $a->tasks()->where('status', 'pending')->get(['id', 'step_code', 'role', 'user_id', 'upazila_id', 'due_at']),
            'logs' => TaskLog::where('application_id', $a->id)->orderBy('id')->get(), 'records' => Record::where('application_id', $a->id)->orderBy('id')->get(['id', 'kind', 'ref_no', 'status', 'published_at', 'created_at']),
            'tasks_done' => $a->tasks()->where('status', 'done')->orderBy('completed_at')->get(['id', 'step_code', 'role', 'completed_at', 'action'])];
    }

    // ---------------------------------------------------------------- lands & owners (wizard step 2)
    private function landData(Request $r, Application $a): array
    {
        $d = $r->validate(['upazila_id' => 'required|integer|exists:upazilas,id', 'mouza_id' => 'nullable|integer|exists:mouzas,id', 'survey_name' => 'nullable|string|max:30', 'jl_no' => 'nullable|string|max:20',
            'khatian_no' => 'required|string|max:30', 'dag_no' => 'required|string|max:30', 'record_class_id' => 'nullable|integer', 'usage_class_id' => 'nullable|integer',
            'total_area_acre' => 'required|numeric|min:0', 'proposed_area_acre' => 'required|numeric|min:0', 'note' => 'nullable|string|max:400',
            'owners' => 'required|array|min:1', 'owners.*.name' => 'required|string|max:150', 'owners.*.nid' => 'nullable|string|max:20', 'owners.*.mobile' => 'nullable|string|max:20',
            'owners.*.email' => 'nullable|email|max:150', 'owners.*.address' => 'nullable|string|max:400', 'owners.*.share' => 'nullable|numeric|min:0|max:1', 'owners.*.mutation_done' => 'boolean', 'owners.*.extra' => 'nullable|array']);
        if ((float) $d['proposed_area_acre'] > (float) $d['total_area_acre'] + 0.00001) throw new WorkflowException('প্রস্তাবিত পরিমাণ দাগের মোট জমির চেয়ে বেশি হতে পারে না');
        if (Upazila::whereKey($d['upazila_id'])->value('district_id') !== $a->district_id) throw new WorkflowException('উপজেলা নির্বাচিত জেলার নয় (আগে জেলা নির্বাচন করুন)');
        return $d;
    }

    private function saveOwners(ApplicationLand $land, array $owners): void
    {
        $land->owners()->delete();
        foreach ($owners as $o) LandOwner::create(['land_id' => $land->id] + $o);
    }

    public function storeLand(Request $r, int $id)
    {
        $a = self::find($r, $id); $this->editable($r, $a);
        $d = $this->landData($r, $a); $owners = $d['owners']; unset($d['owners']);
        $land = ApplicationLand::create($d + ['application_id' => $a->id]);
        $this->saveOwners($land, $owners);
        $a->update(['total_area_acre' => $a->lands()->sum('proposed_area_acre')]);
        return response()->json($land->load('owners'), 201);
    }

    public function updateLand(Request $r, int $landId)
    {
        $land = ApplicationLand::findOrFail($landId); $a = self::find($r, $land->application_id); $this->editable($r, $a);
        $d = $this->landData($r, $a); $owners = $d['owners']; unset($d['owners']);
        $land->update($d + ['cha_status' => 'pending']);
        $this->saveOwners($land, $owners);
        $a->update(['total_area_acre' => $a->lands()->sum('proposed_area_acre')]);
        return $land->load('owners');
    }

    public function destroyLand(Request $r, int $landId)
    {
        $land = ApplicationLand::findOrFail($landId); $a = self::find($r, $land->application_id); $this->editable($r, $a);
        $land->owners()->delete(); $land->delete();
        $a->update(['total_area_acre' => $a->lands()->sum('proposed_area_acre')]);
        return response()->noContent();
    }

    // ---------------------------------------------------------------- attachments
    public function upload(Request $r, int $id)
    {
        $a = self::find($r, $id);
        $d = $r->validate(['kind' => 'required|in:proposal,media,valuation,other', 'slot_no' => 'nullable|integer|min:1|max:25', 'title' => 'nullable|string|max:300', 'expires_on' => 'nullable|date', 'ref_id' => 'nullable|integer', 'file' => 'required|file']);
        if ($d['kind'] === 'proposal') $this->editable($r, $a);
        else abort_unless(in_array($r->user()->role, ['lao', 'kanungo', 'surveyor', 'dc', 'adc', 'rb'], true), 403);
        $f = $r->file('file'); $ext = strtolower($f->getClientOriginalExtension());
        if (! in_array($ext, config('attachments.allowed'), true)) throw new WorkflowException('অনুমোদিত নয় এমন ফাইলের ধরন: ' . $ext);
        $limit = (in_array($ext, ['mp4', 'mov']) ? config('attachments.max_video_mb') : config('attachments.max_file_mb')) * 1024 * 1024;
        if ($f->getSize() > $limit) throw new WorkflowException('ফাইলের আকার সীমা অতিক্রম করেছে');
        if ($d['kind'] === 'proposal') {
            if (! $d['slot_no']) throw new WorkflowException('সংযুক্তির ক্রম নং আবশ্যক');
            foreach (Attachment::where('application_id', $a->id)->where('kind', 'proposal')->where('slot_no', $d['slot_no'])->get() as $old) { Storage::disk('local')->delete($old->path); $old->delete(); }
        }
        $att = Attachment::create(['application_id' => $a->id, 'kind' => $d['kind'], 'slot_no' => $d['slot_no'] ?? null, 'title' => $d['title'] ?? null, 'expires_on' => $d['expires_on'] ?? null, 'ref_id' => $d['ref_id'] ?? null,
            'path' => $f->store('applications/' . $a->id, 'local'), 'original_name' => $f->getClientOriginalName(), 'mime' => $f->getClientMimeType(), 'size' => $f->getSize(), 'uploaded_by' => $r->user()->id, 'status' => 'pending']);
        return response()->json($att, 201);
    }

    public function destroyAttachment(Request $r, int $attId)
    {
        $att = Attachment::findOrFail($attId); $a = self::find($r, $att->application_id);
        if ($att->kind === 'proposal') $this->editable($r, $a); else abort_unless($att->uploaded_by === $r->user()->id, 403);
        Storage::disk('local')->delete($att->path); $att->delete();
        return response()->noContent();
    }

    public function download(Request $r, int $attId)
    {
        $att = Attachment::findOrFail($attId);
        if (! (($att->kind === 'record') && $r->user()->role === 'citizen')) self::find($r, $att->application_id);
        return Storage::disk('local')->download($att->path, $att->original_name);
    }

    /** LAO / kanungo / surveyor mark an attachment as correct or needing correction. */
    public function verify(Request $r, int $attId)
    {
        abort_unless(in_array($r->user()->role, ['lao', 'kanungo', 'surveyor', 'adc', 'dc'], true), 403);
        $att = Attachment::findOrFail($attId); self::find($r, $att->application_id);
        $d = $r->validate(['status' => 'required|in:ok,fix,pending', 'note' => 'nullable|string|max:400']);
        $att->update($d);
        return $att;
    }

    public function readiness(Request $r, int $id, WorkflowService $wf)
    {
        return ['errors' => $wf->readiness(self::find($r, $id))];
    }

    public function submit(Request $r, int $id, WorkflowService $wf)
    {
        $a = self::find($r, $id); $this->editable($r, $a);
        if ($a->status === 'needs_refinement') throw new WorkflowException('সংশোধিত আবেদন দাখিল করতে "পদক্ষেপ" থেকে সংশোধন কাজটি সম্পন্ন করুন');
        return $wf->submit($a, $r->user());
    }
}
