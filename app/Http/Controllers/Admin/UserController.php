<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users', ['users' => User::orderBy('created_at', 'desc')->paginate(20), 'roles' => Role::cases()]);
    }

    /** Privileged and external accounts are provisioned here only. */
    public function store(Request $request)
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_column(Role::cases(), 'value'))],
            'password' => ['required', 'string', 'min:10', 'max:200'],
        ]);
        $user = new User(['name' => $d['name'], 'email' => Str::lower($d['email']), 'password' => $d['password']]);
        $user->role = $d['role'];
        $user->is_active = true;
        $user->save();
        AuditLogger::log('admin.user_created', 'user', (string) $user->getKey(), ['role' => $user->role]);

        return back()->with('status', 'Account created.');
    }

    public function toggle(User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot deactivate yourself.');
        $user->is_active = ! $user->is_active;
        $user->save();
        AuditLogger::log($user->is_active ? 'admin.user_activated' : 'admin.user_deactivated', 'user', (string) $user->getKey());

        return back()->with('status', 'Account updated.');
    }
}
