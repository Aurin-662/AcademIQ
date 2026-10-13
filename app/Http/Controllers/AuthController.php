<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            $user = Auth::user();

            if (!$user->approved && in_array($user->role, ['institution_admin', 'teacher'])) {
                return redirect()->intended('/')->with('warning', 'Welcome, ' . $user->name . '. Note: Your account is currently pending administrator approval.');
            }

            return redirect()->intended('/')->with('success', 'Welcome back, ' . $user->name . '!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        $institutions = Institution::all();
        return view('auth.register', compact('institutions'));
    }

    public function register(Request $request)
    {
        $role = $request->input('role');

        $rules = [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(6)],
            'role'     => 'required|in:employer,teacher,institution_admin',
        ];

        if ($role === 'employer') {
            $rules['organization'] = 'required|string|max:255';
        } elseif ($role === 'institution_admin') {
            $rules['institution_code'] = 'required|string|max:20';
            $rules['institution_name'] = 'required|string|max:255';
        } elseif ($role === 'teacher') {
            $rules['institution_code'] = 'required|string|max:20';
            $rules['department']       = 'required|string|max:255';
            $rules['designation']      = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        $isApproved = ($role === 'employer'); // Employers approved automatically

        $user = User::create([
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'password'         => Hash::make($validated['password']),
            'role'             => $role,
            'institution_code' => isset($validated['institution_code']) ? strtoupper($validated['institution_code']) : null,
            'organization'     => $validated['organization'] ?? null,
            'approved'         => $isApproved,
        ]);

        if ($role === 'institution_admin') {
            $code = strtoupper($validated['institution_code']);
            $existing = Institution::where('code', $code)->first();
            if (!$existing) {
                Institution::create([
                    'code'       => $code,
                    'name'       => $validated['institution_name'],
                    'verified'   => false,
                    'created_at' => now(),
                ]);
            }
        } elseif ($role === 'teacher') {
            $code = strtoupper($validated['institution_code']);
            $teacherId = 'TCHR-' . $code . '-' . str_pad($user->id, 5, '0', STR_PAD_LEFT);
            Teacher::create([
                'teacher_id'       => $teacherId,
                'user_id'          => $user->id,
                'name'             => $user->name,
                'designation'      => $validated['designation'],
                'department'       => $validated['department'],
                'institution_code' => $code,
                'email'            => $user->email,
                'verified'         => false,
            ]);
        }

        Auth::login($user);

        if (!$isApproved) {
            return redirect('/')->with('warning', 'Registration successful! Your account is pending administrator approval before you can issue documents.');
        }

        return redirect('/')->with('success', 'Registration successful! Welcome to AcademIQ.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('info', 'You have been logged out.');
    }
}
