<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AuthorizedIssuer;
use App\Models\Credential;
use App\Models\CredentialDelivery;
use App\Models\CredentialVersion;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Services\Credentials\IssuerService;
use App\Services\Credentials\NodeCrypto;
use Tests\TestCase;

class CredentialTest extends TestCase
{
    private User $admin;
    private AuthorizedIssuer $issuer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['academiq.issuer_key_path' => storage_path('app/issuer-keys-test')]);
        $this->admin = $this->makeUser('admin');
        $this->issuer = app(IssuerService::class)->authorize($this->admin, 'Dr. Test Issuer', 'Controller of Examinations');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(storage_path('app/issuer-keys-test/*.key')) ?: []);
        parent::tearDown();
    }

    /** @return array{0:User,1:\OpenSSLAsymmetricKey} student with a real P-256 wallet key */
    private function student(string $ref = 'KUET-2001', string $email = 'student@example.test'): array
    {
        $u = $this->makeUser('student', ['email' => $email]);
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];
        $raw = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
        StudentProfile::create(['user_id' => (string) $u->getKey(), 'student_reference' => $ref]);
        StudentReference::create(['student_reference' => $ref, 'user_id' => (string) $u->getKey(), 'account_status' => 'active', 'wallet_public_key' => base64_encode($raw)]);

        return [$u, $key];
    }

    private function claims(array $o = []): array
    {
        return $o + ['student_reference' => 'KUET-2001', 'cgpa' => '3.52', 'programme_code' => 1, 'degree_category' => 1, 'issue_date' => '2026-06-01', 'idempotency_key' => 'k-'.uniqid()];
    }

    private function open(array $envelope, \OpenSSLAsymmetricKey $priv): array
    {
        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').base64_decode($envelope['epk']);
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64)."-----END PUBLIC KEY-----\n";
        $secret = openssl_pkey_derive(openssl_pkey_get_public($pem), $priv, 32);
        $key = hash_hkdf('sha256', $secret, 32, 'academiq-delivery-v1', '');
        $ct = base64_decode($envelope['ct']);

        return json_decode(openssl_decrypt(substr($ct, 0, -16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($envelope['iv']), substr($ct, -16)), true);
    }

    public function test_issue_stores_only_commitment_and_seals_valid_package(): void
    {
        [, $priv] = $this->student();
        $this->actingAs($this->admin)->post('/admin/credentials', $this->claims())->assertSessionHasNoErrors();

        $c = Credential::first();
        $this->assertSame('active', $c->credential_status);
        $this->assertSame(1, $c->current_version);
        $this->assertSame(1, CredentialVersion::count());

        // D1 leaks nothing private.
        $raw = json_encode($c->getAttributes()).json_encode(CredentialVersion::first()->getAttributes());
        foreach (['salt', 'signature', 'cgpa', '"R8"'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw);
        }

        // Student can open the sealed package; commitment and issuer signature are valid.
        $pkg = $this->open(CredentialDelivery::first()->envelope, $priv);
        $node = app(NodeCrypto::class);
        $this->assertSame($c->commitment, $pkg['commitment']);
        $this->assertSame($c->commitment, $node->commit($pkg['fields']));
        $this->assertSame(352, $pkg['claims']['cgpa_x100']);
        $this->assertTrue($node->call(['op' => 'verify', 'pub' => $pkg['issuer']['pub'], 'msg' => $pkg['commitment'], 'R8' => $pkg['signature']['R8'], 'S' => $pkg['signature']['S']])['valid']);
        $this->assertTrue(AuditLog::where('action', 'credential.issued')->exists());
    }

    public function test_unauthorized_issuers_and_roles_rejected(): void
    {
        $this->student();
        // issuer-role user with no AuthorizedIssuer record
        $stray = $this->makeUser('issuer', ['email' => 'stray@example.test']);
        $this->actingAs($stray)->post('/admin/credentials', $this->claims())->assertForbidden();
        // suspended issuer
        $this->issuer->update(['authorization_status' => 'suspended']);
        $this->actingAs($this->admin)->post('/admin/credentials', $this->claims())->assertForbidden();
        // student role
        $this->actingAs($this->makeUser('student', ['email' => 'x@example.test']))->post('/admin/credentials', $this->claims())->assertForbidden();
        $this->assertSame(0, Credential::count());
        $this->assertTrue(AuditLog::where('action', 'issuer.authorization_rejected')->exists());
    }

    public function test_requires_wallet_and_valid_input(): void
    {
        $u = $this->makeUser('student', ['email' => 'nw@example.test']);
        StudentReference::create(['student_reference' => 'KUET-NOWALLET', 'user_id' => (string) $u->getKey(), 'account_status' => 'active']);
        $this->actingAs($this->admin)->post('/admin/credentials', $this->claims(['student_reference' => 'KUET-NOWALLET']))->assertSessionHasErrors('student_reference');
        $this->actingAs($this->admin)->post('/admin/credentials', $this->claims(['student_reference' => 'MISSING']))->assertSessionHasErrors('student_reference');
        $this->student();
        foreach ([['cgpa' => '4.50'], ['cgpa' => 'abc'], ['programme_code' => 99], ['degree_category' => 9], ['issue_date' => '2999-01-01']] as $bad) {
            $this->actingAs($this->admin)->post('/admin/credentials', $this->claims($bad))->assertSessionHasErrors(array_key_first($bad));
        }
        $this->assertSame(0, Credential::count());
    }

    public function test_duplicate_submission_is_idempotent(): void
    {
        $this->student();
        $claims = $this->claims();
        $this->actingAs($this->admin)->post('/admin/credentials', $claims);
        $this->actingAs($this->admin)->post('/admin/credentials', $claims);
        $this->assertSame(1, Credential::count());
        $this->assertSame(1, CredentialDelivery::count());
    }

    public function test_update_creates_new_version_and_supersedes_old(): void
    {
        [, $priv] = $this->student();
        $this->actingAs($this->admin)->post('/admin/credentials', $this->claims());
        $id = Credential::first()->credential_id;
        $v1 = Credential::first()->commitment;

        $this->actingAs($this->admin)->put("/admin/credentials/$id", ['cgpa' => '3.80', 'programme_code' => 1, 'degree_category' => 1, 'issue_date' => '2026-07-01'])->assertSessionHasNoErrors();
        $c = Credential::first();
        $this->assertSame(2, $c->current_version);
        $this->assertNotSame($v1, $c->commitment);
        $this->assertSame('superseded', CredentialVersion::where('version', 1)->first()->status);
        $this->assertSame('active', CredentialVersion::where('version', 2)->first()->status);
        $this->assertSame($v1, CredentialVersion::where('version', 1)->first()->commitment); // history preserved

        $d = CredentialDelivery::all(); // undelivered v1 replaced by v2
        $this->assertCount(1, $d);
        $pkg = $this->open($d->first()->envelope, $priv);
        $this->assertSame(2, $pkg['credential_version']);
        $this->assertSame(380, $pkg['claims']['cgpa_x100']);
    }

    public function test_revoke_blocks_further_changes(): void
    {
        $this->student();
        $this->actingAs($this->admin)->post('/admin/credentials', $this->claims());
        $id = Credential::first()->credential_id;
        $this->actingAs($this->admin)->post("/admin/credentials/$id/revoke", ['reason' => 'Issued in error'])->assertSessionHasNoErrors();
        $c = Credential::first();
        $this->assertSame('revoked', $c->credential_status);
        $this->assertNotNull($c->revoked_at);
        $this->assertSame('Issued in error', $c->revocation_reason);
        $this->assertSame(0, CredentialDelivery::count());
        $this->actingAs($this->admin)->put("/admin/credentials/$id", ['cgpa' => '3.0', 'programme_code' => 1, 'degree_category' => 1, 'issue_date' => '2026-07-01'])->assertSessionHasErrors('credential');
        $this->actingAs($this->admin)->post("/admin/credentials/$id/revoke", ['reason' => 'again again'])->assertSessionHasErrors('credential');
        $this->assertTrue(AuditLog::where('action', 'credential.revoked')->exists());
    }

    public function test_student_ownership_and_ack(): void
    {
        [$alice] = $this->student('KUET-A', 'alice@example.test');
        [$bob] = $this->student('KUET-B', 'bob@example.test');
        $this->actingAs($this->admin)->post('/admin/credentials', $this->claims(['student_reference' => 'KUET-A']));
        $id = Credential::first()->credential_id;

        $this->actingAs($alice)->get("/student/credentials/$id")->assertOk();
        $this->actingAs($bob)->get("/student/credentials/$id")->assertForbidden();
        $this->actingAs($bob)->getJson('/student/wallet/credentials')->assertOk()->assertJsonCount(0);
        $this->actingAs($alice)->getJson('/student/wallet/credentials')->assertOk()->assertJsonCount(1);
        $this->actingAs($bob)->getJson('/student/wallet/deliveries')->assertJsonCount(0);

        $delivery = $this->actingAs($alice)->getJson('/student/wallet/deliveries')->assertJsonCount(1)->json('0.id');
        $this->actingAs($bob)->postJson("/student/wallet/deliveries/$delivery/ack")->assertNotFound();
        $this->actingAs($alice)->postJson("/student/wallet/deliveries/$delivery/ack")->assertOk();
        $this->assertSame(0, CredentialDelivery::count()); // ciphertext removed after receipt
    }

    public function test_wallet_key_registration(): void
    {
        $u = $this->makeUser('student', ['email' => 'w@example.test']);
        StudentProfile::create(['user_id' => (string) $u->getKey(), 'student_reference' => 'KUET-W']);
        StudentReference::create(['student_reference' => 'KUET-W', 'user_id' => (string) $u->getKey(), 'account_status' => 'active']);
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];
        $pub = base64_encode("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));

        $this->actingAs($u)->postJson('/student/wallet/key', ['public_key_b64' => base64_encode('short')])->assertStatus(422);
        $this->actingAs($u)->postJson('/student/wallet/key', ['public_key_b64' => $pub])->assertOk();
        $this->actingAs($u)->postJson('/student/wallet/key', ['public_key_b64' => $pub])->assertStatus(409);
        $this->actingAs($u)->postJson('/student/wallet/key', ['public_key_b64' => $pub, 'replace' => true])->assertOk();
    }

    public function test_issuer_key_rotation_and_listing(): void
    {
        $old = $this->issuer->current_key_id;
        $this->actingAs($this->admin)->post("/admin/issuers/{$this->issuer->getKey()}/rotate")->assertSessionHasNoErrors();
        $i = $this->issuer->fresh();
        $this->assertNotSame($old, $i->current_key_id);
        $this->assertSame('retired', collect($i->keys)->firstWhere('key_id', $old)['status']);
        $this->assertSame('active', collect($i->keys)->firstWhere('key_id', $i->current_key_id)['status']);
        $this->actingAs($this->makeUser('issuer', ['email' => 'i@example.test']))->get('/admin/issuers')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/issuers')->assertOk();
        $this->actingAs($this->admin)->get('/admin/credentials')->assertOk();
        $this->actingAs($this->admin)->get('/admin/students')->assertOk();
    }
}
