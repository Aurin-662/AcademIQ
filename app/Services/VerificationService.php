<?php

namespace App\Services;

use App\Jobs\ProcessVerificationFraudCheck;
use App\Models\DocumentRecord;
use App\Models\Teacher;
use App\Models\VerificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    /**
     * Verify a document by document_id
     *
     * @param string $documentId
     * @param Request|null $request
     * @param string $verificationType 'single'|'bulk'
     * @return array
     */
    public function verify(string $documentId, ?Request $request = null, string $verificationType = 'single'): array
    {
        $cacheKey = 'doc_' . $documentId;
        $isHit = Cache::has($cacheKey);

        if ($isHit) {
            Log::info("[Cache HIT] Document verification ID: {$documentId}");
        } else {
            Log::info("[Cache MISS] Document verification ID: {$documentId}");
        }

        $ttl = config('academiq.cache_ttl', 3600);

        // Fetch document cached as plain array
        $doc = Cache::remember($cacheKey, $ttl, function () use ($documentId) {
            $record = DocumentRecord::where('document_id', $documentId)->first();
            if (!$record) {
                return null;
            }

            return [
                'document_id'    => $record->document_id,
                'document_type'  => $record->document_type ?? 'certificate',
                'issuer_type'    => $record->issuer_type ?? 'institution',
                'issuer_id'      => $record->issuer_id,
                'issuer_name'    => $record->issuer_name,
                'recipient_name' => $record->recipient_name,
                'roll_no'        => $record->roll_no,
                'department'     => $record->department,
                'passing_year'   => $record->passing_year ? (int) $record->passing_year : null,
                'result_grade'   => $record->result_grade,
                'purpose'        => $record->purpose,
                'issue_date'     => $record->issue_date instanceof \DateTimeInterface ? $record->issue_date->format('Y-m-d') : (string) $record->issue_date,
                'hash'           => $record->hash,
                'status'         => $record->status ?? 'active',
            ];
        });

        if (!$doc) {
            $result = 'not_found';
            $isUnderReview = false;
            $teacherInfo = null;
        } else {
            // Recompute HMAC hash
            if ($doc['document_type'] === 'recommendation_letter' || $doc['issuer_type'] === 'teacher') {
                $computedHash = DocumentRecord::computeLorHash(
                    $doc['document_id'],
                    $doc['recipient_name'],
                    $doc['issuer_id'],
                    $doc['purpose'] ?? ''
                );
            } else {
                $computedHash = DocumentRecord::computeHash(
                    $doc['document_id'],
                    $doc['recipient_name'],
                    $doc['issuer_id'],
                    $doc['roll_no'] ?? '',
                    $doc['passing_year'] ?? ''
                );
            }

            $isHashValid = hash_equals($doc['hash'] ?? '', $computedHash);

            if (!$isHashValid) {
                $result = 'tampered';
            } elseif ($doc['status'] === 'revoked') {
                $result = 'revoked';
            } else {
                $result = 'genuine';
            }

            $isUnderReview = ($isHashValid && $doc['status'] === 'flagged');

            // Teacher issuer details
            $teacherInfo = null;
            if ($doc['issuer_type'] === 'teacher' || $doc['document_type'] === 'recommendation_letter') {
                $teacher = Teacher::where('teacher_id', $doc['issuer_id'])->first();
                if ($teacher) {
                    $teacherInfo = [
                        'teacher_id'       => $teacher->teacher_id,
                        'name'             => $teacher->name,
                        'designation'      => $teacher->designation,
                        'department'       => $teacher->department,
                        'institution_code' => $teacher->institution_code,
                        'verified'         => (bool) $teacher->verified,
                    ];
                }
            }
        }

        // Determine request metadata
        $ip = $request ? $request->ip() : '127.0.0.1';
        $verifiedBy = $ip;
        if (auth()->check()) {
            $verifiedBy = auth()->user()->organization ?: (auth()->user()->name . ' (' . auth()->user()->email . ')');
        }

        // Write verification log entry
        VerificationLog::create([
            'certificate_id'    => $documentId,
            'verified_by'       => $verifiedBy,
            'verification_type' => $verificationType,
            'result'            => $result,
            'ip_address'        => $ip,
            'timestamp'         => now(),
        ]);

        // Background fraud / anomaly hook dispatch
        if ($verificationType === 'single') {
            try {
                dispatch(new ProcessVerificationFraudCheck($documentId, $verifiedBy, $ip, $result))->afterResponse();
            } catch (\Throwable $e) {
                // Ignore queue issues if running synchronously without workers
            }
        }

        return [
            'document_id'     => $documentId,
            'result'          => $result,
            'is_under_review' => $isUnderReview,
            'document'        => $doc,
            'teacher'         => $teacherInfo,
            'verified_at'     => now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->format('Y-m-d H:i:s T'),
            'cached'          => $isHit,
        ];
    }
}
