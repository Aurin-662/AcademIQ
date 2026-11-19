<x-layouts.app :title="'Request '.$req->request_id">
    <div class="card grid gap-3 text-sm sm:grid-cols-2">
        <p><span class="text-muted">Applicant reference</span><br>{{ $req->student_reference }}</p>
        <p><span class="text-muted">Status</span><br><x-status :value="$req->request_status" /></p>
        <p><span class="text-muted">Submitted</span><br>{{ $req->created_at?->format('Y-m-d H:i') }}</p>
        <p><span class="text-muted">Consent</span><br>
            @if(! $consent) Awaiting applicant (until {{ $req->consent_deadline?->format('Y-m-d') }})
            @else {{ ucfirst($consent->decision) }} · {{ $consent->decision_timestamp?->format('Y-m-d H:i') }}@if($consent->revoked_at) · withdrawn @endif @endif</p>
    </div>
    <h2 class="mb-2 mt-8 text-lg font-bold">Checks</h2>
    <div class="card divide-y divide-slate-soft !p-0">
        @foreach($rows as $r)
        <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm"><span>{{ $r['label'] }}</span><x-status :value="$r['status']" /></div>
        @endforeach
    </div>
    @if($req->request_status === 'completed')
    <h2 class="mb-2 mt-8 text-lg font-bold">Report</h2>
    @if($report)
        <div class="card space-y-3 text-sm">
            <p>Report <span class="font-mono">{{ $report->report_id }}</span> · generated {{ $report->generated_at->format('Y-m-d H:i') }} UTC · overall: <strong>{{ $report->outcome_summary['overall'] }}</strong></p>
            <a class="btn-primary" href="{{ route('employer.reports.download', $report->report_id) }}">Download PDF report</a>
            <div>
                <p class="font-medium">Access history</p>
                @forelse($accesses as $a)
                    <p class="text-xs text-muted">{{ $a->timestamp->format('Y-m-d H:i') }} · {{ $a->action === 'report.downloaded' ? 'downloaded' : $a->action }} · {{ $a->actor_name }}</p>
                @empty
                    <p class="text-xs text-muted">Not accessed yet.</p>
                @endforelse
            </div>
        </div>
    @else
        <p class="card text-sm text-muted">The report is being prepared. Reload in a moment.</p>
    @endif
    @endif
    <p class="mt-3 text-xs text-muted">You see statuses and outcomes only. Credential contents, proofs and participant remarks are never shared.</p>
</x-layouts.app>
