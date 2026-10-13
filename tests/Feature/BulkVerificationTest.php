<?php

namespace Tests\Feature;

use App\Models\BulkVerificationRequest;
use App\Models\DocumentRecord;
use App\Models\User;
use App\Models\VerificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BulkVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DocumentRecord::truncate();
        BulkVerificationRequest::truncate();
        VerificationLog::truncate();
    }

    public function test_bulk_verification_with_three_row_csv(): void
    {
        $employer = User::create([
            'name'         => 'Test Employer',
            'email'        => 'hr@company.com',
            'password'     => bcrypt('password123'),
            'role'         => 'employer',
            'organization' => 'Tech Corp',
            'approved'     => true,
        ]);

        // 1. Genuine Document
        $doc1Id = 'CERT-2024-KUET-BULK01';
        $doc1Hash = DocumentRecord::computeHash($doc1Id, 'Student One', 'KUET', '1807011', 2024);
        DocumentRecord::create([
            'document_id'    => $doc1Id,
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => 'Student One',
            'roll_no'        => '1807011',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.80',
            'issue_date'     => now(),
            'hash'           => $doc1Hash,
            'status'         => 'active',
        ]);

        // 2. Tampered Document (hash mismatch)
        $doc2Id = 'CERT-2024-KUET-BULK02';
        DocumentRecord::create([
            'document_id'    => $doc2Id,
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => 'Tampered Student',
            'roll_no'        => '1807012',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.80',
            'issue_date'     => now(),
            'hash'           => 'invalid_tampered_hash_value',
            'status'         => 'active',
        ]);

        // 3. Fake Document ID (not in database)
        $doc3Id = 'FAKE-CERT-999999';

        // Prepare 3-row CSV
        $csvContent = "document_id\n{$doc1Id}\n{$doc2Id}\n{$doc3Id}\n";
        $file = UploadedFile::fake()->createWithContent('candidates.csv', $csvContent);

        $response = $this->actingAs($employer)->post('/bulk', [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();

        $bulkRecord = BulkVerificationRequest::first();
        $this->assertNotNull($bulkRecord);
        $this->assertEquals(3, $bulkRecord->total_records);
        $this->assertEquals(3, $bulkRecord->processed);
        $this->assertEquals('completed', $bulkRecord->status);

        $summary = $bulkRecord->result_summary;
        $this->assertEquals(1, $summary['genuine']);
        $this->assertEquals(1, $summary['tampered']);
        $this->assertEquals(1, $summary['not_found']);
        $this->assertEquals(0, $summary['revoked']);

        // Check report show page
        $showRes = $this->actingAs($employer)->get('/bulk/' . $bulkRecord->_id);
        $showRes->assertStatus(200);
        $showRes->assertSee('Student One');

        // Check CSV download stream
        $downloadRes = $this->actingAs($employer)->get('/bulk/' . $bulkRecord->_id . '/download');
        $downloadRes->assertStatus(200);
        $downloadRes->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
