<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public static function payload(User $u): array
    {
        return ['id' => $u->id, 'username' => $u->username, 'name_bn' => $u->name_bn, 'role' => $u->role, 'role_label' => config("larms.roles.{$u->role}"),
            'designation' => $u->designation, 'office_name' => $u->office_name, 'division_id' => $u->division_id, 'district_id' => $u->district_id,
            'upazila_id' => $u->upazila_id, 'requiring_body_id' => $u->requiring_body_id, 'nid' => $u->nid, 'mobile' => $u->mobile];
    }

    /** Login with user id, e-mail or mobile number. */
    public function login(Request $r)
    {
        $d = $r->validate(['login' => 'required|string', 'password' => 'required|string']);
        $login = trim($d['login']);
        $u = User::whereRaw('LOWER(username) = ?', [mb_strtolower($login)])->orWhereRaw('LOWER(email) = ?', [mb_strtolower($login)])->orWhere('mobile', $login)->first();
        if (! $u || ! $u->is_active || ! Hash::check($d['password'], $u->password)) {
            throw ValidationException::withMessages(['login' => ['ইউজার আইডি বা পাসওয়ার্ড ভুল']]);
        }
        $plain = Str::random(64);
        ApiToken::create(['user_id' => $u->id, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDays((int) config('larms.token_days'))]);
        $u->forceFill(['last_login_at' => now()])->save();
        return ['token' => $plain, 'user' => self::payload($u)];
    }

    public function me(Request $r) { return self::payload($r->user()); }

    public function logout(Request $r)
    {
        ApiToken::where('token_hash', hash('sha256', (string) $r->bearerToken()))->delete();
        return response()->noContent();
    }

    public function changePassword(Request $r)
    {
        $d = $r->validate(['current' => 'required', 'password' => 'required|string|min:8|confirmed']);
        if (! Hash::check($d['current'], $r->user()->password)) throw ValidationException::withMessages(['current' => ['বর্তমান পাসওয়ার্ড ভুল']]);
        $r->user()->update(['password' => $d['password']]);
        return response()->noContent();
    }
}
