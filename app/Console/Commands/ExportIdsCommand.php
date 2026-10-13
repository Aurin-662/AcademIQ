<?php

namespace App\Console\Commands;

use App\Models\DocumentRecord;
use Illuminate\Console\Command;

class ExportIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academiq:export-ids {count=500 : Number of valid IDs to export} {--path=loadtest/valid_ids.json : Output path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export a list of valid document IDs to a JSON file for k6 load testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $outputPath = base_path($this->option('path'));

        $this->info("Exporting {$count} valid document IDs from MongoDB...");

        $ids = DocumentRecord::where('status', 'active')
            ->limit($count)
            ->pluck('document_id')
            ->all();

        if (empty($ids)) {
            $ids = DocumentRecord::limit($count)->pluck('document_id')->all();
        }

        if (empty($ids)) {
            $this->error("No documents found in database. Seed documents first with `php artisan academiq:seed-large 1000`.");
            return self::FAILURE;
        }

        $dir = dirname($outputPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($outputPath, json_encode($ids, JSON_PRETTY_PRINT));

        $this->info("Successfully exported " . count($ids) . " document IDs to: {$outputPath}");
        return self::SUCCESS;
    }
}
