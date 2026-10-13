<?php

namespace Tests\Feature;

use App\Models\DocumentRecord;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TeacherAndAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DocumentRecord::truncate();
        Teacher::truncate();
        Institution::truncate();
    }

    public function test_teacher_can_issue_lor_and_it_verifies_with_verified_badge(): void
    {
        $institution = Institution::create([
            'code'       => 'KUET',
            'name'       => 'Khulna University of Engineering & Technology',
            'verified'   => true,
            'created_at' => now(),
        ]);

        $teacherUser = User::create([
            'name'             => 'Prof. Tariqul Islam',
            'email'            => 'tariqul@cse.kuet.ac.bd',
            'password'         => bcrypt('password123'),
            'role'             => 'teacher',
            'institution_code' => 'KUET',
            'approved'         => true,
        ]);

        $teacher = Teacher::create([
            'teacher_id'       => 'TCHR-KUET-00001',
            'user_id'          => $teacherUser->id,
            'name'             => $teacherUser->name,
            'designation'      => 'Professor',
            'department'       => 'CSE',
            'institution_code' => 'KUET',
            'email'            => $teacherUser->email,
            'verified'         => true, // Verified faculty
        ]);

        // Issue LOR
        $issueRes = $this->actingAs($teacherUser)->post('/lor', [
            'recipient_name' => 'Aurin Rahman',
            'purpose'        => 'Graduate School Application in MIT',
        ]);

        $issueRes->assertStatus(200);

        $lor = DocumentRecord::where('issuer_id', $teacher->teacher_id)->first();
        $this->assertNotNull($lor);
        $this->assertStringStartsWith('LOR-', $lor->document_id);
        $this->assertEquals('recommendation_letter', $lor->document_type);

        // Verify web result shows genuine and verified faculty
        $verifyRes = $this->get('/verify/' . $lor->document_id);
        $verifyRes->assertStatus(200);
        $verifyRes->assertSee('GENUINE CREDENTIAL');
        $verifyRes->assertSee('Verified Faculty');
        $verifyRes->assertSee('Prof. Tariqul Islam');

        // Test unverified teacher shows "Issuer Not Verified"
        $teacher->update(['verified' => false]);
        Cache::flush();

        $verifyRes2 = $this->get('/verify/' . $lor->document_id);
        $verifyRes2->assertStatus(200);
        $verifyRes2->assertSee('Issuer Not Verified');
    }

    public function test_public_teacher_profile_shows_letters_and_never_shows_confidential_letter_content(): void
    {
        $teacherUser = User::create([
            'name'             => 'Prof. Tariqul Islam',
            'email'            => 'tariqul@cse.kuet.ac.bd',
            'password'         => bcrypt('password123'),
            'role'             => 'teacher',
            'institution_code' => 'KUET',
            'approved'         => true,
        ]);

        $teacher = Teacher::create([
            'teacher_id'       => 'TCHR-KUET-00001',
            'user_id'          => $teacherUser->id,
            'name'             => $teacherUser->name,
            'designation'      => 'Professor',
            'department'       => 'CSE',
            'institution_code' => 'KUET',
            'email'            => $teacherUser->email,
            'verified'         => true,
        ]);

        $docId = 'LOR-2024-KUET-999888';
        $secretBody = 'CONFIDENTIAL: Student showed extraordinary research on deep learning architectures.';
        $hash = DocumentRecord::computeLorHash($docId, 'Candidate Student', $teacher->teacher_id, 'Higher Studies');

        DocumentRecord::create([
            'document_id'    => $docId,
            'document_type'  => 'recommendation_letter',
            'issuer_type'    => 'teacher',
            'issuer_id'      => $teacher->teacher_id,
            'issuer_name'    => $teacher->name,
            'recipient_name' => 'Candidate Student',
            'purpose'        => 'Higher Studies',
            'issue_date'     => now(),
            'hash'           => $hash,
            'status'         => 'active',
        ]);

        $res = $this->get('/teachers/' . $teacher->teacher_id);
        $res->assertStatus(200);
        $res->assertSee('Candidate Student');
        $res->assertSee($docId);
        // Ensure confidential text is NEVER visible
        $res->assertDontSee($secretBody);

        // Filter search: "Did this teacher issue a letter to ___?"
        $searchRes = $this->get('/teachers/' . $teacher->teacher_id . '?recipient=Candidate');
        $searchRes->assertStatus(200);
        $searchRes->assertSee('Candidate Student');

        $emptySearchRes = $this->get('/teachers/' . $teacher->teacher_id . '?recipient=NonexistentPerson');
        $emptySearchRes->assertStatus(200);
        $emptySearchRes->assertDontSee($docId);
    }

    public function test_system_admin_can_revoke_document_with_reason(): void
    {
        $admin = User::create([
            'name'     => 'System Admin',
            'email'    => 'admin@academiq.edu',
            'password' => bcrypt('password123'),
            'role'     => 'system_admin',
            'approved' => true,
        ]);

        $docId = 'CERT-2024-KUET-REVOKE_TEST';
        $hash = DocumentRecord::computeHash($docId, 'Test Student', 'KUET', '1807099', 2024);

        DocumentRecord::create([
            'document_id'    => $docId,
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => 'KUET',
            'issuer_name'    => 'KUET',
            'recipient_name' => 'Test Student',
            'roll_no'        => '1807099',
            'department'     => 'CSE',
            'passing_year'   => 2024,
            'result_grade'   => '3.50',
            'issue_date'     => now(),
            'hash'           => $hash,
            'status'         => 'active',
        ]);

        $response = $this->actingAs($admin)->post('/admin/documents/' . $docId . '/revoke', [
            'reason' => 'Academic misconduct cancellation',
        ]);

        $response->assertRedirect();

        $doc = DocumentRecord::where('document_id', $docId)->first();
        $this->assertEquals('revoked', $doc->status);
        $this->assertEquals('Academic misconduct cancellation', $doc->revocation_reason);

        // Verify public check returns revoked
        $verifyRes = $this->getJson('/api/verify/' . $docId);
        $verifyRes->assertStatus(410);
        $verifyRes->assertJsonFragment(['result' => 'revoked']);
    }
}
