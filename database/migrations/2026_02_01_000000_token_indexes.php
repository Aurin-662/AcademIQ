<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Mongo indexes for claim tokens. NOT run automatically; run only on explicit instruction.
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        $db = Schema::connection('mongodb');
        $db->table('verification_tokens', function ($c) {
            $c->unique('token_id');
            $c->index('student_reference');
        });
        $db->table('token_usage_logs', fn ($c) => $c->index('token_id'));
        $db->table('issuance_batches', fn ($c) => $c->unique('batch_id'));
    }

    public function down(): void {}
};
