<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index(Request $r)
    {
        $q = User::orderBy('role')->orderBy('name_bn');
        if ($r->filled('role')) $q->where('role', $r->query('role'));
        if ($r->filled('q')) $q->where(fn ($x) => $x->where('name_bn', 'like', '%' . $r->query('q') . '%')->orWhere('username', 'like', '%' . $r->query('q') . '%')->orWhere('mobile', 'like', '%' . $r->query('q') . '%'));
        return $q->paginate(50, ['id', 'username', 'name_bn', 'email', 'mobile', 'nid', 'role', 'designation', 'district_id', 'upazila_id', 'requiring_body_id', 'is_active']);
    }

    private function rules(?int $id = null): array
    {
        return ['username' => ['required', 'string', 'max:60', Rule::unique('users', 'username')->ignore($id)], 'name_bn' => 'required|string|max:150', 'name_en' => 'nullable|string|max:150', 'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20', 'nid' => 'nullable|string|max:20', 'role' => ['required', Rule::in(array_keys(config('larms.roles')))], 'designation' => 'nullable|string|max:150', 'office_name' => 'nullable|string|max:200',
            'division_id' => 'nullable|integer', 'district_id' => 'nullable|integer', 'upazila_id' => 'nullable|integer', 'union_name' => 'nullable|string|max:100', 'requiring_body_id' => 'nullable|integer', 'is_active' => 'boolean'];
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->rules() + ['password' => 'nullable|string|min:8']);
        $u = User::create($d + ['password' => $d['password'] ?? config('larms.default_password')]);
        AuditLog::create(['user_id' => $r->user()->id, 'action' => 'user.create', 'entity' => 'user', 'entity_id' => $u->id, 'created_at' => now()]);
        return response()->json($u, 201);
    }

    public function update(Request $r, int $id)
    {
        $u = User::findOrFail($id);
        $u->update($r->validate($this->rules($id)));
        return $u;
    }

    public function resetPassword(Request $r, int $id)
    {
        $d = $r->validate(['password' => 'nullable|string|min:8']);
        User::findOrFail($id)->update(['password' => $d['password'] ?? config('larms.default_password')]);
        return response()->noContent();
    }
}
