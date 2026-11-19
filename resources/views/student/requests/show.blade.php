<x-layouts.app :title="'Request from '.$req->company_name">
    @error('consent')<div class="mb-4 rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad" role="alert">{{ $message }}</div>@enderror
    <div class="card mb-6 grid gap-3 text-sm sm:grid-cols-3">
        <p><span class="text-muted">Employer</span><br>{{ $req->company_name }}</p>
        <p><span class="text-muted">Request</span><br><span class="font-mono">{{ $req->request_id }}</span></p>
        <p><span class="text-muted">Status</span><br><x-status :value="$req->request_status" /></p>
    </div>

    @php($awaiting = $req->request_status === 'awaiting_consent' && ! $req->consent_deadline->isPast())
    <form method="POST" action="{{ route('student.requests.decide', $req->request_id) }}" x-data="{ n: 0 }">
        @csrf
        <h2 class="mb-2 text-lg font-bold">{{ $awaiting ? 'Choose what you consent to' : 'Requested checks' }}</h2>
        <div class="space-y-3">
            @if(isset($scope['academic']))
            <label class="card block">
                <span class="flex items-start gap-3">
                    @if($awaiting)<input class="mt-1" type="checkbox" name="scope[academic]" value="1" checked>@endif
                    <span><span class="font-semibold">Academic claim (zero-knowledge proof)</span><br>
                    <span class="text-sm">{{ \App\Services\Verification\ClaimDescriber::academic($req->requested_checks['academic']) }}</span><br>
                    <span class="text-xs text-muted">Shared: {{ \App\Services\Verification\ClaimDescriber::academicShared() }}</span></span>
                </span>
                @if($consent && ! ($consent->approved_scope['academic'] ?? true))<span class="mt-2 block text-xs text-bad">Declined</span>@endif
            </label>
            @endif
            @foreach($req->requested_checks['background'] as $t)
                @php([$label, $shared] = \App\Services\Verification\ClaimDescriber::background($t['type'], $t['details']))
                <label class="card block">
                    <span class="flex items-start gap-3">
                        @if($awaiting)<input class="mt-1" type="checkbox" name="scope[{{ $t['type'] }}]" value="1" checked>@endif
                        <span><span class="font-semibold">{{ $label }}</span><br><span class="text-xs text-muted">Shared: {{ $shared }}</span></span>
                    </span>
                    @if($consent && ! ($consent->approved_scope[$t['type']] ?? true))<span class="mt-2 block text-xs text-bad">Declined</span>@endif
                </label>
            @endforeach
        </div>

        @if($awaiting)
        <p class="mt-4 text-sm text-muted">Unchecked items are declined and will not run. Decide before {{ $req->consent_deadline->format('Y-m-d') }}. You can withdraw consent later while checks are in progress.</p>
        <div class="mt-4 flex gap-3">
            <button class="btn-primary" name="decision" value="approve" type="submit">Approve selected</button>
            <button class="btn-ghost" name="decision" value="reject" type="submit" onclick="return confirm('Decline this entire request?')">Decline request</button>
        </div>
        @endif
    </form>

    @if($consent)
        <p class="mt-6 text-sm text-muted">Your decision: <strong>{{ $consent->decision }}</strong> on {{ $consent->decision_timestamp?->format('Y-m-d H:i') }}@if($consent->revoked_at) · withdrawn {{ $consent->revoked_at->format('Y-m-d H:i') }}@endif. Authorization reference {{ $consent->authorization_reference }}.</p>
    @endif
    @if($req->request_status === 'in_progress' && $req->academic_state === 'pending')
        <a class="btn-primary mt-4" href="{{ route('student.proof.show', $req->request_id) }}">Generate proof</a>
    @endif
    @if($req->request_status === 'in_progress')
        <form method="POST" action="{{ route('student.requests.revoke', $req->request_id) }}" class="mt-4" onsubmit="return confirm('Withdraw consent? Outstanding checks will be cancelled.')">@csrf<button class="btn-danger" type="submit">Withdraw consent</button></form>
    @endif
</x-layouts.app>
