<?php

namespace App\Console\Commands;

use App\Models\DocumentRecord;
use App\Models\VerificationLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use MongoDB\BSON\UTCDateTime;

class ImportDatasetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academiq:import 
                            {--certificates= : Path to certificates CSV file} 
                            {--logs= : Path to verification logs CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import sample certificates and verification logs CSV datasets into MongoDB';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $certsPath = $this->option('certificates') ?: database_path('data/certificates_sample.csv');
        $logsPath  = $this->option('logs') ?: database_path('data/verification_logs_sample.csv');

        $this->info("Importing dataset into MongoDB database 'academiq'...");

        // 1. Import Certificates
        $this->importCertificates($certsPath);

        // 2. Import Verification Logs
        $this->importLogs($logsPath);

        $this->newLine();
        $this->info('Import process completed successfully!');
        return self::SUCCESS;
    }

    protected function importCertificates(string $filePath): void
    {
        if (!file_exists($filePath)) {
            $this->warn("Certificates CSV file not found at: {$filePath}. Skipping.");
            return;
        }

        $this->info("Reading certificates from: {$filePath}");
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Failed to open {$filePath}");
            return;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            $this->error("Empty CSV file: {$filePath}");
            fclose($handle);
            return;
        }

        // Normalize header
        $header = array_map(fn($col) => strtolower(trim($col)), $header);
        $colMap = array_flip($header);

        $chunk = [];
        $totalImported = 0;
        $batchSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }

            $getVal = fn($key, $default = null) => isset($colMap[$key], $row[$colMap[$key]]) ? trim($row[$colMap[$key]]) : $default;

            $docId       = $getVal('certificate_id') ?: $getVal('document_id');
            $issuerId    = $getVal('institution_code') ?: $getVal('issuer_id', 'KUET');
            $issuerName  = $getVal('institution_name') ?: $getVal('issuer_name', 'University');
            $recipient   = $getVal('student_name') ?: $getVal('recipient_name', 'Student');
            $rollNo      = $getVal('roll_no');
            $department  = $getVal('department');
            $passingYear = (int) $getVal('passing_year', date('Y'));
            $grade       = $getVal('result_grade') ?: $getVal('cgpa');
            $status      = strtolower($getVal('status', 'active'));
            $csvHash     = $getVal('hash', '');
            $issueDateRaw = $getVal('issue_date');

            if (!$docId) {
                continue;
            }

            // Recompute HMAC if status is active. If flagged, preserve CSV hash to demonstrate tamper detection
            if ($status === 'active') {
                $hash = DocumentRecord::computeHash($docId, $recipient, $issuerId, $rollNo, $passingYear);
            } else {
                $hash = $csvHash ?: DocumentRecord::computeHash($docId, $recipient, $issuerId, $rollNo, $passingYear);
            }

            $issueDate = $issueDateRaw ? Carbon::parse($issueDateRaw) : now();

            $chunk[] = [
                'document_id'    => $docId,
                'document_type'  => 'certificate',
                'issuer_type'    => 'institution',
                'issuer_id'      => $issuerId,
                'issuer_name'    => $issuerName,
                'recipient_name' => $recipient,
                'roll_no'        => $rollNo,
                'department'     => $department,
                'passing_year'   => $passingYear,
                'result_grade'   => $grade,
                'purpose'        => null,
                'issue_date'     => new UTCDateTime($issueDate->getTimestamp() * 1000),
                'hash'           => $hash,
                'status'         => $status,
                'created_at'     => new UTCDateTime(now()->getTimestamp() * 1000),
                'updated_at'     => new UTCDateTime(now()->getTimestamp() * 1000),
            ];

            if (count($chunk) >= $batchSize) {
                DocumentRecord::raw(function ($col) use ($chunk) {
                    $col->insertMany($chunk);
                });
                $totalImported += count($chunk);
                $chunk = [];
                $this->output->write(".");
            }
        }

        if (!empty($chunk)) {
            DocumentRecord::raw(function ($col) use ($chunk) {
                $col->insertMany($chunk);
            });
            $totalImported += count($chunk);
        }

        fclose($handle);
        $this->newLine();
        $this->info("Imported {$totalImported} certificates into MongoDB `documents` collection.");
    }

    protected function importLogs(string $filePath): void
    {
        if (!file_exists($filePath)) {
            $this->warn("Logs CSV file not found at: {$filePath}. Skipping.");
            return;
        }

        $this->info("Reading verification logs from: {$filePath}");
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Failed to open {$filePath}");
            return;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            $this->error("Empty CSV file: {$filePath}");
            fclose($handle);
            return;
        }

        $header = array_map(fn($col) => strtolower(trim($col)), $header);
        $colMap = array_flip($header);

        $chunk = [];
        $totalImported = 0;
        $batchSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }

            $getVal = fn($key, $default = null) => isset($colMap[$key], $row[$colMap[$key]]) ? trim($row[$colMap[$key]]) : $default;

            $certId   = $getVal('certificate_id') ?: $getVal('document_id');
            $by       = $getVal('verified_by', 'Anonymous');
            $type     = $getVal('verification_type', 'single');
            $result   = strtolower($getVal('result', 'genuine'));
            $ip       = $getVal('ip_address', '127.0.0.1');
            $tsRaw    = $getVal('timestamp');

            if (!$certId) {
                continue;
            }

            $date = $tsRaw ? Carbon::parse($tsRaw) : now();

            $chunk[] = [
                'certificate_id'    => $certId,
                'verified_by'       => $by,
                'verification_type' => $type,
                'result'            => $result,
                'ip_address'        => $ip,
                'timestamp'         => new UTCDateTime($date->getTimestamp() * 1000),
            ];

            if (count($chunk) >= $batchSize) {
                VerificationLog::raw(function ($col) use ($chunk) {
                    $col->insertMany($chunk);
                });
                $totalImported += count($chunk);
                $chunk = [];
                $this->output->write(".");
            }
        }

        if (!empty($chunk)) {
            VerificationLog::raw(function ($col) use ($chunk) {
                $col->insertMany($chunk);
            });
            $totalImported += count($chunk);
        }

        fclose($handle);
        $this->newLine();
        $this->info("Imported {$totalImported} logs into MongoDB `verification_logs` collection.");
    }
}
