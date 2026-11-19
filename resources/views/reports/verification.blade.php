<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AcademiQ Verification Report {{ $reportId }}</title>
<style>
    @page { margin: 36px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #172B3A; }
    .head { background: #10243A; color: #fff; padding: 16px 18px; }
    .brand { font-size: 20px; font-weight: bold; }
    .brand span { color: #18A999; }
    .inst { font-size: 10px; color: #cfe3ee; margin-top: 3px; }
    h2 { font-size: 13px; color: #0E5B46; border-bottom: 1px solid #E8EEF3; padding-bottom: 3px; margin: 18px 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 6px 8px; text-align: left; vertical-align: top; }
    th { background: #E8EEF3; color: #64748B; font-size: 10px; }
    .kv td:first-child { width: 34%; color: #64748B; }
    .rows td { border-bottom: 1px solid #E8EEF3; }
    .overall { font-size: 15px; font-weight: bold; padding: 10px 14px; margin-top: 14px; border: 2px solid #0E5B46; color: #0E5B46; }
    .overall.bad { border-color: #C84646; color: #C84646; }
    .overall.mid { border-color: #D69E36; color: #8a6414; }
    .note { margin-top: 18px; font-size: 9px; color: #64748B; }
    .auth { margin-top: 14px; padding: 8px; background: #F8FAFC; border: 1px solid #E8EEF3; font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
</style>
</head>
<body>
<div class="head">
    <div class="brand">Academi<span>Q</span> &middot; Verification Report</div>
    <div class="inst">Khulna University of Engineering &amp; Technology (KUET) &middot; Academic Credential Verification System</div>
</div>

<h2>Report</h2>
<table class="kv">
    <tr><td>Report ID</td><td>{{ $reportId }}</td></tr>
    <tr><td>Verification request ID</td><td>{{ $request->request_id }}</td></tr>
    <tr><td>Generated</td><td>{{ $generatedAt->format('Y-m-d H:i') }} UTC</td></tr>
    <tr><td>Applicant reference</td><td>{{ $request->student_reference }}</td></tr>
    <tr><td>Requesting employer</td><td>{{ $request->company_name }}</td></tr>
    <tr><td>Request submitted</td><td>{{ $request->created_at?->format('Y-m-d H:i') }} UTC</td></tr>
</table>

<h2>Applicant consent</h2>
<table class="kv">
    <tr><td>Decision</td><td>{{ $consent ? ucfirst($consent->decision) : 'None recorded' }}</td></tr>
    <tr><td>Decision time</td><td>{{ $consent?->decision_timestamp?->format('Y-m-d H:i') }} UTC</td></tr>
    <tr><td>Authorization reference</td><td>{{ $consent?->authorization_reference }}</td></tr>
    <tr><td>Consented checks</td><td>{{ $approvedLabels ? implode('; ', $approvedLabels) : 'None' }}</td></tr>
</table>

<h2>Outcomes</h2>
<table class="rows">
    <tr><th>Type</th><th>Check</th><th>Outcome</th></tr>
    @foreach($outcomes as $o)
    <tr><td>{{ $o['type'] }}</td><td>{{ $o['check'] }}</td><td><strong>{{ $o['result'] }}</strong></td></tr>
    @endforeach
</table>

<div class="overall {{ $overall === 'Not verified' ? 'bad' : ($overall === 'Incomplete' ? 'mid' : '') }}">Overall status: {{ $overall }}</div>

<div class="auth">
    Report authenticity code: {{ $authCode }}<br>
    Confirm at {{ route('report.verify') }} using the report ID and this code.
</div>

<p class="note">This report contains verification outcomes only. It does not contain the applicant's credential contents, issuer signature, cryptographic proof or private inputs, and it excludes any check the applicant did not consent to. Academic outcomes are established by a zero-knowledge proof verified against KUET issuer-signed credentials. Background responses are submitted manually by authorized participants and are not an official database integration.</p>
</body>
</html>
