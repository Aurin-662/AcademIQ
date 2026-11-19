<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_student_registration_creates_profile_and_logs_in(): void
    {
        $this->post('/register', ['role' => 'student', 'name' => 'S', 'email' => 's@example.test', 'password' => 'long-enough-pw', 'password_confirmation' => 'long-enough-pw', 'student_reference' => 'KUET-2001'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated();
        $this->assertSame('student', User::where('email', 's@example.test')->first()->role);
        $this->assertTrue(AuditLog::where('action', 'auth.register')->exists());
    }

    public function test_cannot_self_register_privileged_roles(): void
    {
        foreach (['admin', 'issuer', 'police_verifier'] as $role) {
            $this->post('/register', ['role' => $role, 'name' => 'X', 'email' => "$role@example.test", 'password' => 'long-enough-pw', 'password_confirmation' => 'long-enough-pw'])
                ->assertSessionHasErrors('role');
        }
        $this->assertSame(0, User::count());
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $this->post('/register', ['role' => 'employer', 'company_name' => 'Acme', 'name' => 'E', 'email' => 'e@example.test', 'password' => 'long-enough-pw', 'password_confirmation' => 'long-enough-pw', 'is_active' => 0, 'admin' => 1]);
        $this->assertSame('employer', User::first()->role);
        $this->assertTrue(User::first()->is_active);
    }

    public function test_login_logout_and_audit(): void
    {
        $this->makeUser('student');
        $this->post('/login', ['email' => 'student@example.test', 'password' => 'correct-horse-battery'])->assertRedirect(route('student.dashboard'));
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.login')->exists());
        $this->assertTrue(AuditLog::where('action', 'auth.logout')->exists());
    }

    public function test_bad_login_throttled_and_inactive_rejected(): void
    {
        RateLimiter::clear('login:student@example.test|127.0.0.1');
        $this->makeUser('student');
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'student@example.test', 'password' => 'wrong-wrong-wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'student@example.test', 'password' => 'correct-horse-battery'])->assertSessionHasErrors('email');
        $this->assertGuest();
        RateLimiter::clear('login:student@example.test|127.0.0.1');

        User::where('email', 'student@example.test')->update(['is_active' => false]);
        $this->post('/login', ['email' => 'student@example.test', 'password' => 'correct-horse-battery'])->assertSessionHasErrors('email');
    }

    public function test_nosql_operator_injection_rejected(): void
    {
        $this->makeUser('student');
        $this->post('/login', ['email' => ['$ne' => 'x'], 'password' => ['$ne' => 'x']])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_role_access_restrictions(): void
    {
        $this->get('/student')->assertRedirect(route('login'));
        $roles = ['student' => '/student', 'employer' => '/employer', 'admin' => '/admin', 'issuer' => '/admin', 'referee' => '/external'];
        foreach ($roles as $role => $own) {
            $u = $this->makeUser($role, ['email' => "$role@example.test"]);
            foreach (['/student', '/employer', '/admin', '/admin/users', '/admin/audit', '/external'] as $url) {
                $expected = ($url === $own || ($role === 'admin' && str_starts_with($url, '/admin'))) ? 200 : 403;
                $this->actingAs($u)->get($url)->assertStatus($expected, "$role -> $url");
            }
        }
    }

    public function test_inactive_user_session_blocked(): void
    {
        $u = $this->makeUser('student', ['is_active' => false]);
        $this->actingAs($u)->get('/student')->assertForbidden();
    }

    public function test_admin_provisions_and_deactivates(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->post('/admin/users', ['name' => 'I', 'email' => 'i@example.test', 'role' => 'issuer', 'password' => 'long-enough-pw'])->assertSessionHasNoErrors();
        $issuer = User::where('email', 'i@example.test')->first();
        $this->assertSame('issuer', $issuer->role);
        $this->actingAs($admin)->post("/admin/users/{$issuer->getKey()}/toggle");
        $this->assertFalse($issuer->fresh()->is_active);
        $this->actingAs($admin)->post("/admin/users/{$admin->getKey()}/toggle")->assertStatus(422);
        $this->actingAs($this->makeUser('student', ['email' => 'z@example.test']))->post('/admin/users', [])->assertForbidden();
    }

    public function test_audit_log_is_append_only(): void
    {
        $log = AuditLog::create(['action' => 'x', 'timestamp' => now()]);
        $this->expectException(LogicException::class);
        $log->update(['action' => 'y']);
    }

    public function test_password_reset_flow(): void
    {
        $u = $this->makeUser('student');
        $this->post('/forgot-password', ['email' => 'student@example.test'])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHas('status');
        $token = \Illuminate\Support\Facades\Password::createToken($u);
        $this->post('/reset-password', ['token' => $token, 'email' => 'student@example.test', 'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password'])->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'student@example.test', 'password' => 'brand-new-password'])->assertRedirect();
        $this->assertAuthenticated();
    }
}
