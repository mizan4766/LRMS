<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Record;
use Illuminate\Http\Request;

class RecordController extends Controller
{
    public function show(Request $r, int $id)
    {
        $rec = Record::findOrFail($id);
        $u = $r->user();
        if ($u->role === 'citizen') {
            abort_unless(($rec->status === 'published' && str_starts_with($rec->kind, 'notice_')) || $rec->created_by === $u->id, 403);
        } else {
            ApplicationController::find($r, $rec->application_id);
        }
        return ['record' => $rec, 'attachments' => Attachment::where('application_id', $rec->application_id)->where('kind', 'record')->where('ref_id', $rec->id)->get(['id', 'original_name', 'size'])];
    }
}
