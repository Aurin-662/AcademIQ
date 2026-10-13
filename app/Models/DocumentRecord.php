<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class DocumentRecord extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'documents';

    protected $fillable = [
        'document_id', 'document_type', 'issuer_type', 'issuer_id',
        'issuer_name', 'recipient_name', 'roll_no', 'department',
        'passing_year', 'result_grade', 'purpose', 'issue_date', 'hash', 'status',
    ];

    protected $casts = [
        'passing_year' => 'integer',
        'issue_date'   => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function ($doc) {
            if (!empty($doc->document_id)) {
                \Illuminate\Support\Facades\Cache::forget('doc_' . $doc->document_id);
            }
        });
        static::deleted(function ($doc) {
            if (!empty($doc->document_id)) {
                \Illuminate\Support\Facades\Cache::forget('doc_' . $doc->document_id);
            }
        });
    }

    /**
     * Compute HMAC-SHA256 for a certificate
     */
    public static function computeHash($documentId, $recipient, $issuerId, $roll, $year): string
    {
        $secret = config('academiq.doc_hmac_secret', env('DOC_HMAC_SECRET', ''));
        return hash_hmac('sha256', implode('|', [
            $documentId,
            $recipient,
            $issuerId,
            $roll ?? '',
            $year ?? '',
        ]), $secret);
    }

    /**
     * Compute HMAC-SHA256 for a recommendation letter (LOR)
     */
    public static function computeLorHash($documentId, $recipient, $issuerId, $purpose): string
    {
        $secret = config('academiq.doc_hmac_secret', env('DOC_HMAC_SECRET', ''));
        return hash_hmac('sha256', implode('|', [
            $documentId,
            $recipient,
            $issuerId,
            $purpose ?? '',
        ]), $secret);
    }
}