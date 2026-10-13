<?php

namespace Tests\Feature;

use App\Models\DocumentRecord;
use App\Models\FraudFlag;
use App\Models\User;
use App\Models\VerificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FraudDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DocumentRecord::truncate();
        FraudFlag::truncate();
        VerificationLog::truncate();
    }

    public function test_duplicate_roll_triggers_fraud_flag_on_issue(): void
    {
        $admin = User::create([
            'name'             => 'KUET Admin',
            'email'            => 'admin@kuet.ac.bd',
            'password'         => bcrypt('password123'),
            'role'             => 'institution_admin',
            'institution_code' => 'KUET',
            'approved'         => true,
        ]);

        // First issue: authentic student
        DocumentRecord::create([
            'document_id'    => 'CERT-2024-KUET-ORIG01',
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => 'Original Student',
            'roll_no'        => '1807099',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.80',
            'issue_date'     => now(),
            'hash'           => 'somehash',
            'status'         => 'active',
        ]);

        // Second issue: same roll, different name
        $response = $this->actingAs($admin)->post('/documents', [
            'student_name' => 'Impostor Student',
            'roll_no'      => '1807099',
            'department'   => 'CSE',
            'passing_year' => 2024,
            'result_grade' => '3.90',
        ]);

        $response->assertStatus(200);

        // Check duplicate_roll fraud flag exists
        $flag = FraudFlag::where('flag_type', 'duplicate_roll')->first();
        $this->assertNotNull($flag);
        $this->assertEquals('pending_review', $flag->status);
    }

    public function test_near_duplicate_name_triggers_fraud_flag_on_issue(): void
    {
        $admin = User::create([
            'name'             => 'KUET Admin',
            'email'            => 'admin@kuet.ac.bd',
            'password'         => bcrypt('password123'),
            'role'             => 'institution_admin',
            'institution_code' => 'KUET',
            'approved'         => true,
        ]);

        // Existing certificate for Mohammed Karim
        DocumentRecord::create([
            'document_id'    => 'CERT-2024-KUET-KARIM1',
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => 'Mohammed Karim',
            'roll_no'        => '1807050',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.80',
            'issue_date'     => now(),
            'hash'           => 'somehash',
            'status'         => 'active',
        ]);

        // Issuing Mohammad Karim (slight spelling variant)
        $response = $this->actingAs($admin)->post('/documents', [
            'student_name' => 'Mohammad Karim',
            'roll_no'      => '1807051',
            'department'   => 'CSE',
            'passing_year' => 2024,
            'result_grade' => '3.85',
        ]);

        $response->assertStatus(200);

        $flag = FraudFlag::where('flag_type', 'near_duplicate_name')->first();
        $this->assertNotNull($flag);
        $this->assertEquals('pending_review', $flag->status);
    }

    public function test_simulate_attack_creates_anomaly_flag(): void
    {
        $exitCode = Artisan::call('academiq:simulate-attack', [
            '--ip'    => '198.51.100.99',
            '--count' => 150,
        ]);

        $this->assertEquals(0, $exitCode);

        $flag = FraudFlag::where('flag_type', 'anomalous_verification_pattern')->first();

        $this->assertNotNull($flag);
        $this->assertEquals('198.51.100.99', $flag->details['ip_address'] ?? null);
        $this->assertTrue($flag->score >= 0.5);
    }

    public function test_fraud_fallback_when_service_is_unreachable(): void
    {
        // Point to an invalid unreachable port
        config(['academiq.fraud_service_url' => 'http://127.0.0.1:59999']);

        $admin = User::create([
            'name'             => 'KUET Admin',
            'email'            => 'admin@kuet.ac.bd',
            'password'         => bcrypt('password123'),
            'role'             => 'institution_admin',
            'institution_code' => 'KUET',
            'approved'         => true,
        ]);

        DocumentRecord::create([
            'document_id'    => 'CERT-2024-KUET-ORIG02',
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => 'Original Person',
            'roll_no'        => '1807088',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.80',
            'issue_date'     => now(),
            'hash'           => 'somehash',
            'status'         => 'active',
        ]);

        // Issuing must still succeed cleanly!
        $response = $this->actingAs($admin)->post('/documents', [
            'student_name' => 'Different Person',
            'roll_no'      => '1807088', // Duplicate roll
            'department'   => 'CSE',
            'passing_year' => 2024,
            'result_grade' => '3.90',
        ]);

        $response->assertStatus(200);

        // Fallback rule-based engine must have caught duplicate roll
        $flag = FraudFlag::where('flag_type', 'duplicate_roll')->first();
        $this->assertNotNull($flag);
    }
}
