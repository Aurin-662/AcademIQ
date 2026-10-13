<?php

namespace App\Console\Commands;

use App\Models\DocumentRecord;
use App\Services\VerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BenchmarkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academiq:benchmark {--samples=1000 : Number of random document IDs to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Measure document verification lookup performance before indexes, after indexes, and with cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $requestedSamples = (int) $this->option('samples');
        $totalDocs = DocumentRecord::count();

        if ($totalDocs === 0) {
            $this->error("No documents found in MongoDB `documents` collection. Please run `php artisan academiq:seed-large 5000` first.");
            return self::FAILURE;
        }

        $sampleSize = min($requestedSamples, $totalDocs);
        $this->info("Fetching {$sampleSize} random document IDs from {$totalDocs} total documents in MongoDB...");

        // Select random document IDs
        $docs = DocumentRecord::raw(function ($col) use ($sampleSize) {
            return $col->aggregate([
                ['$sample' => ['size' => $sampleSize]],
                ['$project' => ['document_id' => 1, '_id' => 0]],
            ]);
        });

        $ids = [];
        foreach ($docs as $d) {
            if (isset($d['document_id'])) {
                $ids[] = $d['document_id'];
            }
        }

        if (empty($ids)) {
            $ids = DocumentRecord::limit($sampleSize)->pluck('document_id')->all();
        }

        $sampleCount = count($ids);
        $this->info("Selected {$sampleCount} test IDs. Beginning 3-phase benchmark...");
        $this->newLine();

        $verificationService = app(VerificationService::class);

        // --- PHASE A: Indexes Dropped (Cold Cache) ---
        $this->warn(">>> Phase A: Dropping custom indexes & flushing cache...");
        Cache::flush();
        $this->dropDocumentIndexes();

        $timingsA = [];
        $startA = microtime(true);
        foreach ($ids as $id) {
            $t0 = microtime(true);
            $verificationService->verify($id, null, 'bulk');
            $timingsA[] = (microtime(true) - $t0) * 1000;
        }
        $totalTimeA = (microtime(true) - $startA) * 1000;
        $statsA = $this->calculateStats($timingsA, $totalTimeA);
        $this->line("Phase A complete. Avg: {$statsA['avg_ms']} ms");

        // --- PHASE B: With Indexes Created (Cold Cache) ---
        $this->warn(">>> Phase B: Creating indexes & flushing cache...");
        Artisan::call('academiq:indexes');
        Cache::flush();

        $timingsB = [];
        $startB = microtime(true);
        foreach ($ids as $id) {
            $t0 = microtime(true);
            $verificationService->verify($id, null, 'bulk');
            $timingsB[] = (microtime(true) - $t0) * 1000;
        }
        $totalTimeB = (microtime(true) - $startB) * 1000;
        $statsB = $this->calculateStats($timingsB, $totalTimeB);
        $this->line("Phase B complete. Avg: {$statsB['avg_ms']} ms");

        // --- PHASE C: With Indexes + Cache Warm ---
        $this->warn(">>> Phase C: Measuring with warm cache...");
        $timingsC = [];
        $startC = microtime(true);
        foreach ($ids as $id) {
            $t0 = microtime(true);
            $verificationService->verify($id, null, 'bulk');
            $timingsC[] = (microtime(true) - $t0) * 1000;
        }
        $totalTimeC = (microtime(true) - $startC) * 1000;
        $statsC = $this->calculateStats($timingsC, $totalTimeC);
        $this->line("Phase C complete. Avg: {$statsC['avg_ms']} ms");

        // --- Print Table ---
        $this->newLine();
        $this->info("================================================================================");
        $this->info("                ACADEMIQ PERFORMANCE BENCHMARK RESULTS                         ");
        $this->info("                Database Records: {$totalDocs} | Test Samples: {$sampleCount}   ");
        $this->info("================================================================================");
        $this->table(
            ['Configuration', 'Avg Latency (ms)', 'p95 Latency (ms)', 'Min (ms)', 'Max (ms)', 'Throughput (req/sec)', 'Speedup'],
            [
                ['(a) No Indexes (Cold Cache)', $statsA['avg_ms'], $statsA['p95_ms'], $statsA['min_ms'], $statsA['max_ms'], $statsA['rps'], '1.0x (Baseline)'],
                ['(b) Indexed (Cold Cache)', $statsB['avg_ms'], $statsB['p95_ms'], $statsB['min_ms'], $statsB['max_ms'], $statsB['rps'], round($statsA['avg_ms'] / max($statsB['avg_ms'], 0.001), 1) . 'x'],
                ['(c) Indexed + Cache Warm', $statsC['avg_ms'], $statsC['p95_ms'], $statsC['min_ms'], $statsC['max_ms'], $statsC['rps'], round($statsA['avg_ms'] / max($statsC['avg_ms'], 0.001), 1) . 'x'],
            ]
        );
        $this->info("================================================================================");

        // --- Append to docs/performance.md ---
        $this->recordPerformanceResults($totalDocs, $sampleCount, $statsA, $statsB, $statsC);

        return self::SUCCESS;
    }

    protected function dropDocumentIndexes(): void
    {
        try {
            $db = DB::connection('mongodb')->getMongoDB();
            $indexes = $db->selectCollection('documents')->listIndexes();
            foreach ($indexes as $index) {
                if ($index->getName() !== '_id_') {
                    $db->selectCollection('documents')->dropIndex($index->getName());
                }
            }
        } catch (\Throwable $e) {
            $this->warn("Index drop note: " . $e->getMessage());
        }
    }

    protected function calculateStats(array $timings, float $totalTimeMs): array
    {
        sort($timings);
        $count = count($timings);
        $sum = array_sum($timings);
        $avg = $count > 0 ? ($sum / $count) : 0;
        $min = $count > 0 ? $timings[0] : 0;
        $max = $count > 0 ? $timings[$count - 1] : 0;
        $p95Index = (int) floor($count * 0.95);
        $p95 = $count > 0 ? $timings[min($p95Index, $count - 1)] : 0;
        $rps = $totalTimeMs > 0 ? round(($count / ($totalTimeMs / 1000)), 1) : 0;

        return [
            'avg_ms' => round($avg, 3),
            'p95_ms' => round($p95, 3),
            'min_ms' => round($min, 3),
            'max_ms' => round($max, 3),
            'rps'    => $rps,
        ];
    }

    protected function recordPerformanceResults(int $totalRecords, int $sampleSize, array $a, array $b, array $c): void
    {
        $docsDir = base_path('docs');
        if (!file_exists($docsDir)) {
            mkdir($docsDir, 0777, true);
        }

        $perfFile = base_path('docs/performance.md');
        $date = now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->format('Y-m-d H:i:s T');

        $speedupIndex = round($a['avg_ms'] / max($b['avg_ms'], 0.001), 1);
        $speedupCache = round($a['avg_ms'] / max($c['avg_ms'], 0.001), 1);

        $markdownContent = "\n### Benchmark Run: {$date}\n";
        $markdownContent .= "- **Total Database Documents:** {$totalRecords}\n";
        $markdownContent .= "- **Sample Lookups:** {$sampleSize}\n\n";
        $markdownContent .= "| Configuration | Avg Latency (ms) | p95 Latency (ms) | Min (ms) | Max (ms) | Throughput (req/s) | Speedup |\n";
        $markdownContent .= "|---|---|---|---|---|---|---|\n";
        $markdownContent .= "| **(a) Unindexed (Cold)** | {$a['avg_ms']} | {$a['p95_ms']} | {$a['min_ms']} | {$a['max_ms']} | {$a['rps']} | Baseline (1.0x) |\n";
        $markdownContent .= "| **(b) Indexed (Cold)** | {$b['avg_ms']} | {$b['p95_ms']} | {$b['min_ms']} | {$b['max_ms']} | {$b['rps']} | {$speedupIndex}x |\n";
        $markdownContent .= "| **(c) Indexed + Cache Warm** | {$c['avg_ms']} | {$c['p95_ms']} | {$c['min_ms']} | {$c['max_ms']} | {$c['rps']} | {$speedupCache}x |\n";
        $markdownContent .= "\n---\n";

        if (!file_exists($perfFile)) {
            $header = "# AcademIQ Performance & Benchmark Engineering Report\n\n";
            $header .= "This document records empirical latency, throughput, and optimization benchmarks.\n";
            file_put_contents($perfFile, $header . $markdownContent);
        } else {
            file_put_contents($perfFile, $markdownContent, FILE_APPEND);
        }

        $this->info("Results written to: docs/performance.md");
    }
}
