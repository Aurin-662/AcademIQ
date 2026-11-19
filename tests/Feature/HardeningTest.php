<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\AuthorizedIssuer;
use App\Models\Credential;
use App\Models\EmployerProfile;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VerificationKey;
use App\Services\Notifier;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    public function test_security_headers_present(): void
    {
        $r = $this->get('/');
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src [^;]*'unsafe-inline'/", $csp);
        $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy');
    }

    public function test_error_pages_render(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found');
        $this->actingAs($this->makeUser('student'))->get('/admin')->assertForbidden()->assertSee('Access denied');
    }

    public function test_notifications_are_private_and_markable(): void
    {
        $a = $this->makeUser('student', ['email' => 'a@example.test']);
        $b = $this->makeUser('student', ['email' => 'b@example.test']);
        Notifier::send((string) $a->getKey(), 'x', 'For Alice only', 'body', url('/student'));
        $id = (string) UserNotification::first()->getKey();

        $this->actingAs($b)->get('/notifications')->assertOk()->assertDontSee('For Alice only');
        $this->actingAs($b)->post("/notifications/$id")->assertNotFound();
        $this->assertNull(UserNotification::first()->read_at);

        $this->actingAs($a)->get('/notifications')->assertOk()->assertSee('For Alice only');
        $this->actingAs($a)->post("/notifications/$id")->assertRedirect(url('/student'));
        $this->assertNotNull(UserNotification::first()->read_at);
        $this->actingAs($a)->get('/student')->assertOk(); // layout bell renders
    }

    public function test_notification_redirect_only_follows_same_site_links(): void
    {
        $a = $this->makeUser('student', ['email' => 'a@example.test']);
        Notifier::send((string) $a->getKey(), 'x', 'External', '', 'https://evil.example/phish');
        $this->actingAs($a)->post('/notifications/'.UserNotification::first()->getKey())->assertRedirect(route('notifications'));
    }

    public function test_email_sent_only_when_a_real_mailer_is_configured(): void
    {
        Mail::fake();
        $u = $this->makeUser('student', ['email' => 'mail@example.test']);

        Notifier::send((string) $u->getKey(), 'x', 'No mailer', 'b'); // tests run with MAIL_MAILER=array
        Mail::assertNothingSent();
        $this->assertNull(UserNotification::first()->emailed_at);

        config(['mail.default' => 'smtp']);
        Notifier::send((string) $u->getKey(), 'x', 'With mailer', 'b');
        Mail::assertSent(NotificationMail::class, 1);
        $this->assertSame('mail@example.test', Mail::sent(NotificationMail::class)->first()->to[0]['address']);
        $this->assertNotNull(UserNotification::where('title', 'With mailer')->first()->emailed_at);
    }

    public function test_email_failure_is_not_reported_as_delivered(): void
    {
        $u = $this->makeUser('student', ['email' => 'mail@example.test']);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
        Notifier::send((string) $u->getKey(), 'x', 'Unreachable', 'b');
        $n = UserNotification::first();
        $this->assertNotNull($n); // in-app copy kept
        $this->assertNull($n->emailed_at); // not marked delivered
    }

    public function test_notifications_created_for_credential_lifecycle(): void
    {
        config(['academiq.issuer_key_path' => storage_path('app/issuer-keys-test')]);
        $admin = $this->makeUser('admin');
        app(\App\Services\Credentials\IssuerService::class)->authorize($admin, 'Dr T', 'Reg');
        $s = $this->makeUser('student', ['email' => 's@example.test']);
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];
        StudentProfile::create(['user_id' => (string) $s->getKey(), 'student_reference' => 'KUET-H1']);
        StudentReference::create(['student_reference' => 'KUET-H1', 'user_id' => (string) $s->getKey(), 'account_status' => 'active', 'wallet_public_key' => base64_encode("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))]);

        $svc = app(\App\Services\Credentials\CredentialService::class);
        $c = $svc->issue($admin, ['student_reference' => 'KUET-H1', 'cgpa' => '3.5', 'programme_code' => 1, 'degree_category' => 1, 'issue_date' => '2026-06-01']);
        $svc->update($admin, $c->credential_id, ['cgpa' => '3.6', 'programme_code' => 1, 'degree_category' => 1, 'issue_date' => '2026-06-02']);
        $svc->revoke($admin, $c->credential_id, 'Issued in error');
        $types = UserNotification::where('user_id', (string) $s->getKey())->pluck('type')->sort()->values()->all();
        $this->assertSame(['credential_issued', 'credential_revoked', 'credential_updated'], $types);
        array_map('unlink', glob(storage_path('app/issuer-keys-test/*.key')) ?: []);
    }

    public function test_admin_dashboard_statistics_come_from_data(): void
    {
        $admin = $this->makeUser('admin');
        Credential::create(['credential_id' => 'C1', 'student_reference' => 'S1', 'credential_status' => 'active']);
        Credential::create(['credential_id' => 'C2', 'student_reference' => 'S1', 'credential_status' => 'revoked']);
        Credential::create(['credential_id' => 'C3', 'student_reference' => 'S1', 'credential_status' => 'active']);
        $page = $this->actingAs($admin)->get('/admin')->assertOk();
        $stats = $page->viewData('stats');
        $this->assertSame(2, $stats['Active credentials']);
        $this->assertSame(1, $stats['Revoked credentials']);
        $page->assertSee('Credentials issued, last 14 days');
    }

    public function test_demo_command_is_repeatable_and_env_guarded(): void
    {
        config(['academiq.issuer_key_path' => storage_path('app/issuer-keys-test')]); // never touch the dev key directory
        $this->artisan('academiq:demo')->assertSuccessful();
        $this->artisan('academiq:demo')->assertSuccessful(); // second run: no duplicates
        $this->assertSame(8, User::count());
        $this->assertSame(2, AuthorizedIssuer::count());
        $this->assertSame(2, StudentReference::count());
        $this->assertSame(1, EmployerProfile::count());
        $this->assertSame(1, VerificationKey::where('circuit_id', 'credential')->count());
        foreach (['admin', 'issuer', 'student', 'employer', 'police_verifier', 'referee', 'previous_employer'] as $role) {
            $this->assertTrue(User::where('role', $role)->exists(), $role);
        }
        $this->post('/login', ['email' => 'student1@demo.academiq.test', 'password' => 'demo-password-123'])->assertRedirect(route('student.dashboard'));

        $this->app['env'] = 'production';
        $this->artisan('academiq:demo')->assertFailed();
        $this->app['env'] = 'testing';
        array_map('unlink', glob(storage_path('app/issuer-keys-test/*.key')) ?: []);
    }
}
