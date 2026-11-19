<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => $request->string('email')->toString()]);

        // Same response either way: no account enumeration.
        return back()->with('status', 'If that email has an account, a reset link has been sent.');
    }

    public function update(Request $request)
    {
        $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed']]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                AuditLogger::log('auth.password_reset', 'user', (string) $user->getKey(), [], $user);
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password updated. You can sign in.')
            : back()->withErrors(['email' => 'This reset link is invalid or expired.']);
    }
}
