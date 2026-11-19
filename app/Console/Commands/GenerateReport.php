<?php

namespace App\Console\Commands;

use App\Services\Reports\ReportService;
use Illuminate\Console\Command;

class GenerateReport extends Command
{
    protected $signature = 'academiq:generate-report {request_id}';
    protected $description = 'Generate (or return the existing) verification report for a completed request.';

    public function handle(ReportService $svc): int
    {
        $r = $svc->generate($this->argument('request_id'));
        $r ? $this->info("Report {$r->report_id}") : $this->error('Request not found or not completed.');

        return $r ? 0 : 1;
    }
}
