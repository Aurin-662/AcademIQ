<?php

namespace Tests\Feature;

use App\Models\DocumentRecord;
use App\Models\User;
use App\Models\VerificationLog;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_system_admin_can_access_admin_analytics(): void
    {
        $admin = User::create([
            'name'     => 'System Admin',
            'email'    => 'admin@academiq.edu',
            'password' => bcrypt('password123'),
            'role'     => 'system_admin',
            'approved' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/analytics');
        $response->assertStatus(200);
        $response->assertSee('System-Wide Verification & Fraud Analytics', false);
        $response->assertSee('chartPerDay');
        $response->assertSee('chartBreakdown');
        $response->assertSee('chartTopInst');
    }

    public function test_institution_admin_can_access_own_institution_analytics(): void
    {
        $kuetAdmin = User::create([
            'name'             => 'KUET Admin',
            'email'            => 'admin@kuet.ac.bd',
            'password'         => bcrypt('password123'),
            'role'             => 'institution_admin',
            'institution_code' => 'KUET',
            'approved'         => true,
        ]);

        $response = $this->actingAs($kuetAdmin)->get('/institution/analytics');
        $response->assertStatus(200);
        $response->assertSee('KUET Credential Verification Analytics');
        $response->assertSee('Institution Code: KUET');
    }

    public function test_guest_and_employer_cannot_access_admin_analytics(): void
    {
        $guestRes = $this->get('/admin/analytics');
        $guestRes->assertRedirect('/login');

        $employer = User::create([
            'name'     => 'Employer User',
            'email'    => 'employer@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'employer',
            'approved' => true,
        ]);

        $empRes = $this->actingAs($employer)->get('/admin/analytics');
        $empRes->assertStatus(403);
    }

    public function test_analytics_pipelines_return_query_times_and_data(): void
    {
        $analytics = app(AnalyticsService::class);

        $perDay = $analytics->getVerificationsPerDay();
        $this->assertArrayHasKey('labels', $perDay);
        $this->assertArrayHasKey('data', $perDay);
        $this->assertArrayHasKey('query_time_ms', $perDay);

        $breakdown = $analytics->getResultBreakdown();
        $this->assertArrayHasKey('labels', $breakdown);
        $this->assertArrayHasKey('data', $breakdown);
        $this->assertArrayHasKey('query_time_ms', $breakdown);

        $topInst = $analytics->getTopInstitutions(5);
        $this->assertArrayHasKey('labels', $topInst);
        $this->assertArrayHasKey('data', $topInst);

        $byHour = $analytics->getVerificationsByHour();
        $this->assertArrayHasKey('labels', $byHour);
        $this->assertEquals(24, count($byHour['labels']));
    }
}
