<?php

namespace App\Jobs;

use App\Models\BulkVerificationRequest;
use App\Models\DocumentRecord;
use App\Models\VerificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use MongoDB\BSON\UTCDateTime;

class ProcessBulkVerificationJob implements ShouldQueue
{
    use Queueable;

    public string $requestId;
    public array $rows; // list of candidate document_ids extracted from CSV
    public string $verifiedBy;
    public string $ipAddress;

    /**
     * Create a new job instance.
     */
    public function __construct(string $requestId, array $rows, string $verifiedBy, string $ipAddress)
    {
        $this->requestId  = $requestId;
        $this->rows       = $rows;
        $this->verifiedBy = $verifiedBy;
        $this->ipAddress  = $ipAddress;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $req = BulkVerificationRequest::where('_id', $this->requestId)
            ->orWhere('id', $this->requestId)
            ->first();

        if (!$req) {
            return;
        }

        $summary = [
            'genuine'   => 0,
            'tampered'  => 0,
            'not_found' => 0,
            'revoked'   => 0,
        ];
        $results = [];
        $logBatch = [];

        $chunks = array_chunk($this->rows, 500);
        $nowBson = new UTCDateTime(now()->getTimestamp() * 1000);

        foreach ($chunks as $chunkIds) {
            // Fetch batch in single query per chunk
            $records = DocumentRecord::whereIn('document_id', $chunkIds)->get()->keyBy('document_id');

            foreach ($chunkIds as $docId) {
                $docId = trim($docId);
                if ($docId === '') {
                    continue;
                }

                $record = $records->get($docId);

                if (!$record) {
                    $result = 'not_found';
                    $recipientName = 'N/A';
                } else {
                    $recipientName = $record->recipient_name;

                    // Recompute HMAC
                    if ($record->document_type === 'recommendation_letter' || $record->issuer_type === 'teacher') {
                        $computedHash = DocumentRecord::computeLorHash(
                            $record->document_id,
                            $record->recipient_name,
                            $record->issuer_id,
                            $record->purpose ?? ''
                        );
                    } else {
                        $computedHash = DocumentRecord::computeHash(
                            $record->document_id,
                            $record->recipient_name,
                            $record->issuer_id,
                            $record->roll_no ?? '',
                            $record->passing_year ?? ''
                        );
                    }

                    $isHashValid = hash_equals($record->hash ?? '', $computedHash);

                    if (!$isHashValid) {
                        $result = 'tampered';
                    } elseif ($record->status === 'revoked') {
                        $result = 'revoked';
                    } else {
                        $result = 'genuine';
                    }
                }

                $summary[$result] = ($summary[$result] ?? 0) + 1;

                $results[] = [
                    'document_id'    => $docId,
                    'recipient_name' => $recipientName,
                    'result'         => $result,
                ];

                $logBatch[] = [
                    'certificate_id'    => $docId,
                    'verified_by'       => $this->verifiedBy,
                    'verification_type' => 'bulk',
                    'result'            => $result,
                    'ip_address'        => $this->ipAddress,
                    'timestamp'         => $nowBson,
                ];
            }

            // Insert logs in chunks of 500
            if (count($logBatch) >= 500) {
                VerificationLog::raw(function ($col) use ($logBatch) {
                    $col->insertMany($logBatch);
                });
                $logBatch = [];
            }
        }

        if (!empty($logBatch)) {
            VerificationLog::raw(function ($col) use ($logBatch) {
                $col->insertMany($logBatch);
            });
        }

        $req->processed      = count($results);
        $req->status         = 'completed';
        $req->result_summary = $summary;
        $req->results        = $results;
        $req->save();
    }
}
