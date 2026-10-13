<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CreateIndexesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academiq:indexes {--drop : Drop existing custom indexes before re-creating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create performance indexes on MongoDB collections using the Schema builder';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Configuring MongoDB indexes for AcademIQ...');

        if ($this->option('drop')) {
            $this->warn('Dropping custom indexes on documents, verification_logs, and fraud_flags...');
            try {
                $db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
                foreach (['documents', 'verification_logs', 'fraud_flags'] as $col) {
                    $indexes = $db->selectCollection($col)->listIndexes();
                    foreach ($indexes as $index) {
                        if ($index->getName() !== '_id_') {
                            $db->selectCollection($col)->dropIndex($index->getName());
                        }
                    }
                }
                $this->info('Indexes dropped successfully.');
            } catch (\Throwable $e) {
                $this->error('Error dropping indexes: ' . $e->getMessage());
            }
            return self::SUCCESS;
        }

        try {
            // 1. documents indexes
            $this->line('Creating indexes on `documents`...');
            Schema::connection('mongodb')->table('documents', function ($collection) {
                $collection->unique('document_id');
                $collection->index(['issuer_id' => 1, 'roll_no' => 1]);
                $collection->index('recipient_name');
            });
            $this->info('✓ `documents` indexed: unique(document_id), compound(issuer_id, roll_no), index(recipient_name)');

            // 2. verification_logs indexes
            $this->line('Creating indexes on `verification_logs`...');
            Schema::connection('mongodb')->table('verification_logs', function ($collection) {
                $collection->index(['certificate_id' => 1, 'timestamp' => -1]);
                $collection->index('timestamp');
            });
            $this->info('✓ `verification_logs` indexed: compound(certificate_id, timestamp), index(timestamp)');

            // 3. fraud_flags indexes
            $this->line('Creating indexes on `fraud_flags`...');
            Schema::connection('mongodb')->table('fraud_flags', function ($collection) {
                $collection->index(['status' => 1, 'detected_at' => -1]);
            });
            $this->info('✓ `fraud_flags` indexed: compound(status, detected_at)');

            $this->newLine();
            $this->info('All MongoDB performance indexes created successfully!');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to create indexes: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
