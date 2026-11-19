<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Credential;
use App\Models\CredentialDelivery;
use App\Models\IssuanceBatch;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Services\Credentials\BulkImport;
use App\Services\Credentials\IssuerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use ZipArchive;

class BulkIssueTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['academiq.issuer_key_path' => storage_path('app/issuer-keys-test')]);
        $this->admin = $this->makeUser('admin');
        app(IssuerService::class)->authorize($this->admin, 'Dr. Test Issuer', 'Controller of Examinations');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(storage_path('app/issuer-keys-test/*.key')) ?: []);
        parent::tearDown();
    }

    private function student(string $ref, bool $wallet = true, string $dept = 'CSE'): void
    {
        $u = $this->makeUser('student', ['email' => strtolower($ref).'@example.test', 'name' => 'Student '.$ref]);
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];
        $raw = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
        StudentProfile::create(['user_id' => (string) $u->getKey(), 'student_reference' => $ref, 'department' => $dept]);
        StudentReference::create(['student_reference' => $ref, 'user_id' => (string) $u->getKey(), 'account_status' => 'active', 'wallet_public_key' => $wallet ? base64_encode($raw) : null]);
    }

    private function csv(array $rows): UploadedFile
    {
        $lines = ['student_id,name,institution,degree,department,cgpa,passing_year'];
        foreach ($rows as $r) {
            $lines[] = implode(',', $r);
        }

        return UploadedFile::fake()->createWithContent('import.csv', implode("\n", $lines));
    }

    private function preview(UploadedFile $f)
    {
        return $this->actingAs($this->admin)->post('/admin/import/preview', ['file' => $f]);
    }

    public function test_preview_flags_every_row_problem(): void
    {
        $this->student('B1');
        $this->student('B2', false);
        $res = $this->preview($this->csv([
            ['B1', 'Student B1', 'KUET', 'BSc', 'CSE', '3.52', '2025'],   // ok
            ['B1', 'Student B1', 'KUET', 'BSc', 'CSE', '3.52', '2025'],   // duplicate
            ['B2', 'x', 'KUET', 'BSc', 'CSE', '3.00', '2025'],            // no wallet
            ['NOPE', 'x', 'KUET', 'BSc', 'CSE', '3.00', '2025'],          // unknown student
            ['B1X', 'x', 'KUET', 'BSc', 'CSE', '4.50', '2025'],           // bad cgpa
            ['B1Y', 'x', 'KUET', 'BSc', 'CSE', '3.123', '2025'],          // 3 decimals
            ['B1Z', 'x', 'Oxford', 'BSc', 'CSE', '3.0', '2025'],          // unknown institution
            ['B1W', 'x', 'KUET', 'BSc', 'CSE', '3.0', '1800'],            // bad year
            ['B1V', 'x', 'KUET', 'BSc', 'CSE', '', '2025'],               // missing cgpa
        ]))->assertOk();
        $p = $res->viewData('preview');
        $this->assertSame(1, $res->viewData('validCount'));
        $this->assertSame([], $p[2]['errors']);
        $this->assertStringContainsString('Duplicate', $p[3]['errors'][0]);
        $this->assertStringContainsString('wallet', $p[4]['errors'][0]);
        $this->assertStringContainsString('No active student', $p[5]['errors'][0]);
        $this->assertStringContainsString('CGPA', implode('; ', $p[6]['errors']));
        $this->assertStringContainsString('2 decimals', implode('; ', $p[7]['errors']));
        $this->assertStringContainsString("Unknown institution", implode('; ', $p[8]['errors']));
        $this->assertStringContainsString('Passing year', implode('; ', $p[9]['errors']));
        $this->assertStringContainsString('Missing cgpa', implode('; ', $p[10]['errors']));
    }

    public function test_commit_issues_valid_rows_once_and_reports_failures(): void
    {
        foreach (['C1', 'C2', 'C3'] as $r) {
            $this->student($r);
        }
        $res = $this->preview($this->csv([
            ['C1', 'Student C1', 'KUET', 'BSc', 'CSE', '3.52', '2025'],
            ['C2', 'Student C2', 'KUET', 'MSc', 'Electrical & Electronic Engineering', '3.9', '2024'],
            ['C3', 'x', 'KUET', 'BSc', 'CSE', '9', '2025'],
        ]));
        $id = $res->viewData('importId');
        $this->assertNotNull(Cache::get('import:'.$id));

        $r = $this->actingAs($this->admin)->post('/admin/import/commit', ['import_id' => $id]);
        $batch = IssuanceBatch::first();
        $r->assertRedirect(route('admin.import.result', $batch->batch_id));
        $this->assertSame([2, 1], [$batch->issued_count, $batch->failed_count]);
        $this->assertSame(2, Credential::where('batch_id', $batch->batch_id)->count());
        $this->assertSame(2, CredentialDelivery::count());
        $this->assertSame(64, strlen($batch->batch_digest));
        $this->assertSame(2, AuditLog::where('action', 'credential.issued')->count());
        $this->assertSame(1, AuditLog::where('action', 'credential.batch_issued')->count());
        $this->assertStringNotContainsString('3.52', json_encode($batch->toArray())); // no claim values stored in the batch record

        // double submit: staged import already consumed
        $this->actingAs($this->admin)->post('/admin/import/commit', ['import_id' => $id])->assertSessionHasErrors('file');
        $this->assertSame(2, Credential::count());

        $csv = $this->actingAs($this->admin)->get("/admin/import/batches/{$batch->batch_id}/errors.csv")->assertOk()->streamedContent();
        $this->assertStringContainsString('C3', $csv);
        $this->assertStringContainsString('CGPA', $csv);
    }

    public function test_other_issuer_cannot_commit_staged_import(): void
    {
        $this->student('D1');
        $id = $this->preview($this->csv([['D1', 'Student D1', 'KUET', 'BSc', 'CSE', '3.5', '2025']]))->viewData('importId');
        $other = $this->makeUser('issuer', ['email' => 'other@example.test']);
        $this->actingAs($other)->post('/admin/import/commit', ['import_id' => $id])->assertSessionHasErrors('file');
        $this->assertSame(0, Credential::count());
    }

    public function test_bulk_revoke_by_student_list(): void
    {
        foreach (['R1', 'R2'] as $r) {
            $this->student($r);
        }
        $id = $this->preview($this->csv([
            ['R1', 'Student R1', 'KUET', 'BSc', 'CSE', '3.5', '2025'], ['R2', 'Student R2', 'KUET', 'BSc', 'CSE', '3.5', '2025'],
        ]))->viewData('importId');
        $this->actingAs($this->admin)->post('/admin/import/commit', ['import_id' => $id]);

        $this->actingAs($this->admin)->post('/admin/import/revoke', ['student_ids' => "R1, R2\nGHOST", 'reason' => 'Issued in error'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Revoked 2') && str_contains($s, 'GHOST'));
        $this->assertSame(2, Credential::where('credential_status', 'revoked')->count());
    }

    public function test_lookup_and_non_issuer_access(): void
    {
        $this->student('L1', true, 'Computer Science & Engineering');
        $this->actingAs($this->admin)->getJson('/admin/students/L1/lookup')->assertOk()
            ->assertJson(['found' => true, 'name' => 'Student L1', 'programme_code' => 1, 'wallet_ready' => true]);
        $this->actingAs($this->admin)->getJson('/admin/students/NOPE/lookup')->assertNotFound();
        $this->actingAs($this->makeUser('student', ['email' => 's@example.test']))->get('/admin/import')->assertForbidden();
    }

    public function test_xlsx_is_parsed(): void
    {
        $this->student('X1');
        $strings = ['student_id', 'name', 'institution', 'degree', 'department', 'cgpa', 'passing_year', 'X1', 'Student X1', 'KUET', 'BSc', 'CSE'];
        $si = implode('', array_map(fn ($s) => "<si><t>$s</t></si>", $strings));
        $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'.implode('', array_map(fn ($i) => '<c r="'.chr(65 + $i).'1" t="s"><v>'.$i.'</v></c>', range(0, 6))).'</row>'
            .'<row r="2"><c r="A2" t="s"><v>7</v></c><c r="B2" t="s"><v>8</v></c><c r="C2" t="s"><v>9</v></c><c r="D2" t="s"><v>10</v></c><c r="E2" t="s"><v>11</v></c><c r="F2"><v>3.52</v></c><c r="G2"><v>2025</v></c></row>'
            .'</sheetData></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE);
        $z->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$si.'</sst>');
        $z->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $z->close();

        $rows = app(BulkImport::class)->parse(new UploadedFile($path, 'import.xlsx', null, null, true));
        $v = app(BulkImport::class)->validate($rows);
        $this->assertSame([], $v['invalid']);
        $this->assertSame(['student_reference' => 'X1', 'cgpa' => '3.52', 'programme_code' => 1, 'degree_category' => 1, 'institution_code' => 1, 'passing_year' => 2025, 'issue_date' => now()->toDateString()], $v['valid'][2]);
        @unlink($path);
    }
}
