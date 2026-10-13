<?php

namespace App\Console\Commands;

use App\Models\DocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;

class SeedLargeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academiq:seed-large {count=200000 : Total number of synthetic documents to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a large dataset of synthetic documents with valid HMAC signatures in batches of 5,000 for load testing & benchmarking';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetCount = (int) $this->argument('count');
        if ($targetCount <= 0) {
            $this->error('Count must be greater than 0.');
            return self::FAILURE;
        }

        $this->info("Preparing to seed {$targetCount} synthetic documents with valid HMAC-SHA256 signatures...");

        $institutions = [
            'KUET' => 'Khulna University of Engineering & Technology',
            'BUET' => 'Bangladesh University of Engineering and Technology',
            'RUET' => 'Rajshahi University of Engineering & Technology',
            'CUET' => 'Chittagong University of Engineering & Technology',
            'DU'   => 'University of Dhaka',
        ];

        $firstNames = ['Mohammad', 'Md.', 'Tanvir', 'Aurin', 'Rakib', 'Sadia', 'Farhana', 'Tahmid', 'Nafis', 'Anik', 'Zubair', 'Nusrat', 'Shakil', 'Sumaiya', 'Mehedi'];
        $lastNames  = ['Rahman', 'Islam', 'Hasan', 'Ahmed', 'Hossain', 'Chowdhury', 'Khan', 'Sarker', 'Alam', 'Mahmud', 'Karim', 'Akter', 'Paul', 'Das'];
        $departments = ['Computer Science and Engineering', 'Electrical and Electronic Engineering', 'Mechanical Engineering', 'Civil Engineering', 'Biomedical Engineering'];

        $instCodes = array_keys($institutions);
        $batchSize = 5000;
        $insertedSoFar = 0;
        $start = microtime(true);

        $nowMs = now()->getTimestamp() * 1000;

        while ($insertedSoFar < $targetCount) {
            $currentBatchSize = min($batchSize, $targetCount - $insertedSoFar);
            $batch = [];

            for ($i = 0; $i < $currentBatchSize; $i++) {
                $code = $instCodes[array_rand($instCodes)];
                $instName = $institutions[$code];
                $student = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
                $dept = $departments[array_rand($departments)];
                $year = rand(2015, 2024);
                $roll = (string) rand(1501001, 2409999);
                $cgpa = number_format(rand(300, 400) / 100, 2);
                $docId = 'CERT-' . $year . '-' . $code . '-' . strtoupper(Str::random(6));

                $hash = DocumentRecord::computeHash($docId, $student, $code, $roll, $year);

                $batch[] = [
                    'document_id'    => $docId,
                    'document_type'  => 'certificate',
                    'issuer_type'    => 'institution',
                    'issuer_id'      => $code,
                    'issuer_name'    => $instName,
                    'recipient_name' => $student,
                    'roll_no'        => $roll,
                    'department'     => $dept,
                    'passing_year'   => $year,
                    'result_grade'   => $cgpa,
                    'purpose'        => null,
                    'issue_date'     => new UTCDateTime($nowMs),
                    'hash'           => $hash,
                    'status'         => 'active',
                    'created_at'     => new UTCDateTime($nowMs),
                    'updated_at'     => new UTCDateTime($nowMs),
                ];
            }

            DocumentRecord::raw(function ($col) use ($batch) {
                $col->insertMany($batch);
            });

            $insertedSoFar += $currentBatchSize;
            $percent = round(($insertedSoFar / $targetCount) * 100, 1);
            $elapsed = round(microtime(true) - $start, 1);
            $this->line("Seeded {$insertedSoFar} / {$targetCount} ({$percent}%) in {$elapsed}s");
        }

        $totalTime = round(microtime(true) - $start, 2);
        $this->newLine();
        $this->info("Successfully generated and inserted {$insertedSoFar} documents in {$totalTime}s!");
        return self::SUCCESS;
    }
}
