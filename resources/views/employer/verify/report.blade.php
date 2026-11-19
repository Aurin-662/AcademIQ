<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AcademiQ token check {{ $log->getKey() }}</title>
<style>
    @page { margin: 36px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #172B3A; }
    .head { background: #10243A; color: #fff; padding: 16px 18px; }
    .brand { font-size: 20px; font-weight: bold; }
    .brand span { color: #18A999; }
    h2 { font-size: 13px; color: #0E5B46; border-bottom: 1px solid #E8EEF3; padding-bottom: 3px; margin: 18px 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 6px 8px; text-align: left; border-bottom: 1px solid #E8EEF3; }
    th { background: #E8EEF3; color: #64748B; font-size: 10px; }
    .overall { font-size: 15px; font-weight: bold; padding: 10px 14px; margin-top: 14px; border: 2px solid; }
    .PASS { color: #0E5B46; } .FAIL, .INVALID { color: #C84646; } .NP { color: #8a6414; }
    .note { margin-top: 18px; font-size: 9px; color: #64748B; }
</style>
</head>
<body>
@php($o = $log->valid ? $log->overall : 'INVALID TOKEN')
@php($cls = ['PASS' => 'PASS', 'FAIL' => 'FAIL', 'NOT PROVEN' => 'NP'][$log->overall] ?? 'INVALID')
<div class="head"><div class="brand">Academi<span>Q</span> &middot; Token Verification Report</div></div>
<table style="margin-top:12px"><tr><td style="width:30%;color:#64748B">Token</td><td>{{ $log->token_id }}</td></tr>
<tr><td style="color:#64748B">Checked</td><td>{{ $log->created_at?->format('Y-m-d H:i') }} UTC</td></tr>
<tr><td style="color:#64748B">Verifier</td><td>{{ $log->verifier_name }}</td></tr>
<tr><td style="color:#64748B">Reference</td><td>{{ $log->getKey() }}</td></tr></table>
<div class="overall {{ $log->valid ? $cls : 'INVALID' }}" style="border-color:currentColor">Overall: {{ $o }}</div>
@if($log->valid)
<h2>Criteria</h2>
<table><thead><tr><th>Criterion</th><th>Result</th></tr></thead><tbody>
@foreach($log->results as $r)<tr><td>{{ $r['label'] }}</td><td><strong>{{ $r['result'] }}</strong></td></tr>@endforeach
</tbody></table>
@else
<p>{{ \App\Services\Verification\TokenVerifier::reasonText($log->reason) }}</p>
@endif
<p class="note">Outcomes only. PASS and FAIL derive from verified zero-knowledge proof flags; NOT PROVEN means the holder did not include the claim or the proven range cannot settle it. No hidden credential data is held by or shown to the verifier.</p>
</body>
</html>
