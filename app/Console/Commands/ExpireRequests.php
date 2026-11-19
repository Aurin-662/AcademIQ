<?php

namespace App\Console\Commands;

use App\Models\BackgroundVerification;
use App\Models\VerificationRequest;
use App\Services\AuditLogger;
use App\Services\Verification\RequestCollector;
use Illuminate\Console\Command;

class ExpireRequests extends Command
{
    protected $signature = 'academiq:expire-requests';
    protected $description = 'Expire requests awaiting consent past their deadline and background tasks past due.';

    public function handle(RequestCollector $collector): int
    {
        foreach (VerificationRequest::where('request_status', 'awaiting_consent')->where('consent_deadline', '<', now())->get() as $r) {
            if (VerificationRequest::where('request_id', $r->request_id)->where('request_status', 'awaiting_consent')->update(['request_status' => 'expired']) === 1) {
                AuditLogger::log('request.expired', 'verification_request', $r->request_id);
            }
        }
        foreach (BackgroundVerification::where('status', 'sent')->where('due_at', '<', now())->get() as $t) {
            if (BackgroundVerification::where('_id', $t->getKey())->where('status', 'sent')->update(['status' => 'expired']) === 1) {
                AuditLogger::log('background.expired', 'verification_request', $t->request_id, ['type' => $t->verification_type]);
                $collector->refresh($t->request_id);
            }
        }

        return 0;
    }
}
