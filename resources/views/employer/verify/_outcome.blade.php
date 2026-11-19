@php
    $overall = $log->overall;
    $tone = $overall === 'PASS' ? 'text-ok border-ok/40 bg-ok/10' : ($overall === 'NOT PROVEN' ? 'text-warn border-warn/40 bg-warn/15' : 'text-bad border-bad/30 bg-bad/10');
    $text = fn ($r) => $r === 'PASS' ? 'text-ok' : ($r === 'FAIL' ? 'text-bad' : 'text-warn');
@endphp
<div class="rounded-lg border p-4 {{ $tone }}" role="status">
    <p class="text-sm">Overall result</p>
    <p class="font-display text-2xl font-bold">{{ $log->valid ? $overall : 'INVALID TOKEN' }}</p>
    @unless($log->valid)<p class="mt-1 text-sm">{{ \App\Services\Verification\TokenVerifier::reasonText($log->reason) }}</p>@endunless
</div>
@if($log->valid)
<div class="card overflow-x-auto !p-0">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Criterion</th><th class="px-4 py-2">Result</th></tr></thead>
        <tbody class="divide-y divide-slate-soft">
        @foreach($log->results as $r)
            <tr><td class="px-4 py-2">{{ $r['label'] }}</td><td class="px-4 py-2 font-semibold {{ $text($r['result']) }}">{{ $r['result'] }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@if($log->revealed_cgpa !== null)<p class="text-sm text-muted">The holder chose to reveal the CGPA: {{ number_format($log->revealed_cgpa, 2) }}.</p>@endif
<p class="text-sm text-muted">PASS and FAIL come from verified zero-knowledge flags. NOT PROVEN means the holder did not include that claim, or the token's proven range cannot settle it. Hidden values are not available to the verifier.</p>
@endif
<p class="text-xs text-muted">Token {{ $log->token_id }} · checked {{ $log->created_at?->format('Y-m-d H:i') }} UTC by {{ $log->verifier_name }} · reference {{ $log->getKey() }}</p>
