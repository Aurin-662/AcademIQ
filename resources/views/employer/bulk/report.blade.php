<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AcademiQ batch {{ $b->batch_id }}</title>
<style>
    @page { margin: 30px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #172B3A; }
    .head { background: #10243A; color: #fff; padding: 12px 16px; font-size: 16px; font-weight: bold; }
    .head span { color: #18A999; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    td, th { padding: 4px 5px; text-align: left; border-bottom: 1px solid #E6EAEF; vertical-align: top; }
    th { background: #E6EAEF; color: #5B6B7B; font-size: 8px; }
    .PASS { color: #0E5B46; font-weight: bold; } .FAIL, .INVALID { color: #B83A3A; font-weight: bold; } .NP { color: #8a6414; font-weight: bold; }
    .kv td { border: 0; padding: 2px 4px; } .note { margin-top: 12px; font-size: 8px; color: #5B6B7B; }
</style>
</head>
<body>
@php($cls = ['PASS' => 'PASS', 'FAIL' => 'FAIL', 'NOT PROVEN' => 'NP', 'INVALID' => 'INVALID'])
<div class="head">Academi<span>Q</span> &middot; Bulk Verification Report</div>
<table class="kv"><tr><td style="width:14%;color:#5B6B7B">Batch</td><td>{{ $b->batch_id }}</td><td style="width:14%;color:#5B6B7B">Verifier</td><td>{{ $b->verifier_name }}</td></tr>
<tr><td style="color:#5B6B7B">Finished</td><td>{{ $b->finished_at?->format('Y-m-d H:i') }} UTC</td><td style="color:#5B6B7B">Tokens</td><td>{{ $p['total'] }} (PASS {{ $p['counts']['PASS'] }}, FAIL {{ $p['counts']['FAIL'] }}, NOT PROVEN {{ $p['counts']['NOT PROVEN'] }}, INVALID {{ $p['counts']['INVALID'] }}, DUPLICATE {{ $p['counts']['DUPLICATE'] }})</td></tr>
<tr><td style="color:#5B6B7B">Criteria</td><td colspan="3">{{ collect($b->criteria)->pluck('label')->implode(' · ') }}</td></tr></table>
<table>
<thead><tr><th>#</th><th>Token</th><th>Candidate</th>@foreach($b->criteria as $c)<th>{{ $c['label'] }}</th>@endforeach<th>Overall</th><th>Reason</th></tr></thead>
<tbody>
@foreach($items as $i)
@php($by = array_column($i->results ?? [], 'result', 'key'))
<tr><td>{{ $i->row }}</td><td>{{ $i->token_id ?? $i->input }}</td><td>{{ $i->candidate_name }} {{ $i->cv_ref }}</td>
@foreach($b->criteria as $c)<td class="{{ $cls[$by[$c['key']] ?? ''] ?? '' }}">{{ $by[$c['key']] ?? '' }}</td>@endforeach
<td class="{{ $cls[$i->overall] ?? '' }}">{{ $i->overall }}</td><td>{{ \App\Services\Verification\BatchVerifier::reasonText($i->reason) }}</td></tr>
@endforeach
</tbody></table>
<p class="note">Outcomes only. PASS and FAIL derive from verified zero-knowledge proof flags; NOT PROVEN means the holder did not include the claim or the proven range cannot settle it. No hidden credential data is held by or shown to the verifier.</p>
</body>
</html>
