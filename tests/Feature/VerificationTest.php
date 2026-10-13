<?php

namespace Tests\Feature;

use App\Models\DocumentRecord;
use App\Models\User;
use App\Models\VerificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DocumentRecord::truncate();
        VerificationLog::truncate();
    }

    public function test_issue_document_and_verify_genuine(): void
    {
        $admin = User::create([
            'name'             => 'KUET Admin',
            'email'            => 'admin@kuet.ac.bd',
            'password'         => bcrypt('password123'),
            'role'             => 'institution_admin',
            'institution_code' => 'KUET',
            'approved'         => true,
        ]);

        $response = $this->actingAs($admin)->post('/documents', [
            'student_name' => 'Mohammad Tanvir',
            'roll_no'      => '1807001',
            'department'   => 'CSE',
            'passing_year' => 2024,
            'result_grade' => '3.89',
        ]);

        $response->assertStatus(200);

        $doc = DocumentRecord::where('roll_no', '1807001')->first();
        $this->assertNotNull($doc);

        // Verify web page
        $verifyRes = $this->get('/verify/' . $doc->document_id);
        $verifyRes->assertStatus(200);
        $verifyRes->assertSee('GENUINE CREDENTIAL');

        // Verify API endpoint
        $apiRes = $this->getJson('/api/verify/' . $doc->document_id);
        $apiRes->assertStatus(200);
        $apiRes->assertJsonFragment([
            'result'      => 'genuine',
            'document_id' => $doc->document_id,
        ]);

        // Check verification log created
        $this->assertDatabaseHas('verification_logs', [
            'certificate_id' => $doc->document_id,
            'result'         => 'genuine',
        ], 'mongodb');
    }

    public function test_tampered_document_detection(): void
    {
        $docId = 'CERT-2024-KUET-TAMPER1';
        $originalName = 'Authentic Student';
        $hash = DocumentRecord::computeHash($docId, $originalName, 'KUET', '1807002', 2024);

        // Save original in MongoDB
        $doc = DocumentRecord::create([
            'document_id'    => $docId,
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => $originalName,
            'roll_no'        => '1807002',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.90',
            'issue_date'     => now(),
            'hash'           => $hash,
            'status'         => 'active',
        ]);

        // Simulate direct tamper in MongoDB without recalculating HMAC (e.g. via Compass)
        // Bypass model events to avoid auto-hashing
        DocumentRecord::raw(function ($collection) use ($docId) {
            $collection->updateOne(
                ['document_id' => $docId],
                ['$set' => ['recipient_name' => 'Tampered Hacker Name']]
            );
        });
        Cache::flush();

        // Verify web page
        $verifyRes = $this->get('/verify/' . $docId);
        $verifyRes->assertStatus(200);
        $verifyRes->assertSee('TAMPERED / FORGERY DETECTED');

        // Verify API endpoint
        $apiRes = $this->getJson('/api/verify/' . $docId);
        $apiRes->assertStatus(422);
        $apiRes->assertJsonFragment([
            'result' => 'tampered',
        ]);
    }

    public function test_unknown_document_returns_not_found(): void
    {
        $verifyRes = $this->get('/verify/UNKNOWN-CERT-999');
        $verifyRes->assertStatus(200);
        $verifyRes->assertSee('NOT FOUND');

        $apiRes = $this->getJson('/api/verify/UNKNOWN-CERT-999');
        $apiRes->assertStatus(404);
        $apiRes->assertJsonFragment([
            'result' => 'not_found',
        ]);
    }

    public function test_revoked_document_returns_revoked(): void
    {
        $docId = 'CERT-2024-KUET-REVOKE1';
        $hash = DocumentRecord::computeHash($docId, 'Some Student', 'KUET', '1807003', 2024);

        DocumentRecord::create([
            'document_id'    => $docId,
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => 'Some Student',
            'roll_no'        => '1807003',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.50',
            'issue_date'     => now(),
            'hash'           => $hash,
            'status'         => 'revoked',
        ]);

        $apiRes = $this->getJson('/api/verify/' . $docId);
        $apiRes->assertStatus(410);
        $apiRes->assertJsonFragment([
            'result' => 'revoked',
        ]);
    }

    public function test_caching_serves_second_request_from_cache(): void
    {
        $docId = 'CERT-2024-KUET-CACHE1';
        $hash = DocumentRecord::computeHash($docId, 'Cache Student', 'KUET', '1807004', 2024);

        DocumentRecord::create([
            'document_id'    => $docId,
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => 'Cache Student',
            'roll_no'        => '1807004',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.50',
            'issue_date'     => now(),
            'hash'           => $hash,
            'status'         => 'active',
        ]);

        Cache::flush();

        // First verify (Cache MISS)
        $res1 = $this->getJson('/api/verify/' . $docId);
        $res1->assertStatus(200);
        $this->assertFalse($res1->json('cached'));

        // Second verify (Cache HIT)
        $res2 = $this->getJson('/api/verify/' . $docId);
        $res2->assertStatus(200);
        $this->assertTrue($res2->json('cached'));
    }
}
