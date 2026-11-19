<?php

namespace App\Listeners;

use App\Events\VerificationCompleted;
use App\Services\AuditLogger;
use App\Services\Reports\ReportService;
use Throwable;

class GenerateVerificationReport
{
    public function handle(VerificationCompleted $event): void
    {
        try {
            app(ReportService::class)->generate($event->requestId);
        } catch (Throwable $e) {
            // A rendering failure must not undo completion; retry with: php artisan academiq:generate-report <request_id>
            report($e);
            AuditLogger::log('report.failed', 'verification_request', $event->requestId);
        }
    }
}
