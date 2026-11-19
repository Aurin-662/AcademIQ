<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\EmployerProfile;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:200']]);
        $email = Str::lower($data['email']);
        $key = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            AuditLogger::log('auth.login_throttled', 'user', $email);
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        // validate() guarantees strings, which blocks NoSQL operator injection via array inputs.
        $user = User::where('email', $email)->first();
        $ok = $user && $user->is_active && Auth::attempt(['email' => $email, 'password' => $data['password']], $request->boolean('remember'));

        if (! $ok) {
            RateLimiter::hit($key, 300);
            AuditLogger::log('auth.login_failed', 'user', $email);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        AuditLogger::log('auth.login', 'user', (string) Auth::id());

        return redirect()->intended(Auth::user()->roleEnum()->home());
    }

    public function register(Request $request)
    {
        $base = $request->validate([
            'role' => ['required', Rule::in(Role::selfRegistrable())],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed'],
        ]);
        $extra = $base['role'] === Role::Student->value
            ? $request->validate([
                'student_reference' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/', Rule::unique('student_profiles', 'student_reference')],
                'department' => ['nullable', 'string', 'max:120'],
            ])
            : $request->validate(['company_name' => ['required', 'string', 'max:160']]);

        $user = new User(['name' => $base['name'], 'email' => Str::lower($base['email']), 'password' => $base['password']]);
        $user->role = $base['role'];
        $user->is_active = true;
        $user->save();

        if ($base['role'] === Role::Student->value) {
            \App\Models\StudentReference::create(['student_reference' => $extra['student_reference'], 'user_id' => (string) $user->getKey(), 'account_status' => 'active', 'wallet_public_key' => null]);
            StudentProfile::create(['user_id' => (string) $user->getKey(), 'student_reference' => $extra['student_reference'], 'department' => $extra['department'] ?? null]);
        } else {
            EmployerProfile::create(['user_id' => (string) $user->getKey(), 'company_name' => $extra['company_name']]);
        }

        AuditLogger::log('auth.register', 'user', (string) $user->getKey(), ['role' => $user->role], $user);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->roleEnum()->home());
    }

    public function logout(Request $request)
    {
        AuditLogger::log('auth.logout', 'user', (string) Auth::id());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
