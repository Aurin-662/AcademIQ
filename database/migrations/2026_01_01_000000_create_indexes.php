<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Mongo indexes and uniqueness guarantees. NOT run automatically; run only on explicit instruction.
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        $db = Schema::connection('mongodb');
        $unique = ['users' => ['email'], 'student_profiles' => ['student_reference', 'user_id'], 'student_references' => ['student_reference'], 'employer_profiles' => ['user_id'],
            'credentials' => ['credential_id'], 'authorized_issuers' => ['issuer_id', 'user_id'], 'verification_requests' => ['request_id'],
            'proof_submissions' => ['request_id'], 'proof_results' => ['request_id'], 'verification_reports' => ['report_id', 'request_id'], 'consent_records' => ['request_id']];
        foreach ($unique as $collection => $fields) {
            $db->table($collection, function ($c) use ($fields) {
                foreach ($fields as $f) {
                    $c->unique($f);
                }
            });
        }
        $db->table('credentials', fn ($c) => $c->index('student_reference'));
        $db->table('credential_versions', fn ($c) => $c->index(['credential_id', 'version']));
        $db->table('background_verifications', function ($c) {
            $c->index('request_id');
            $c->index('participant_reference');
        });
        $db->table('audit_logs', function ($c) {
            $c->index('timestamp');
            $c->index('action');
            $c->index('actor_id');
            $c->index('entity_reference');
        });
        $db->table('notifications', fn ($c) => $c->index('user_id'));
    }

    public function down(): void
    {
        // indexes left in place intentionally
    }
};
