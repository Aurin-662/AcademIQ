<x-layouts.public title="For verifiers">
<x-page-header eyebrow="Verifier guide" title="Verify a graduate in under a minute" lead="For employers and admissions offices. Scan a QR code or enter a token, choose your criteria, and get a clear result." />
@include('pages._role', [
    'steps' => [
        ['Open the token', 'Type the token ID, paste the link, scan the QR code with your camera, or upload a picture of it. Verifiers sign in first, so every check is attributable.'],
        ['Choose your criteria', 'Pick any combination of: CGPA at least a value, passing year (exact or range), institution, degree and department. Only claims the graduate included are offered.'],
        ['Read the result', 'Each criterion shows PASS, FAIL or NOT PROVEN, followed by an overall verdict. NOT PROVEN means the graduate did not include that claim; it is never treated as a pass.'],
        ['Keep a record', 'Print the result or download a PDF report with outcomes only. Each check is logged for audit and shown to the graduate.'],
    ],
    'sees' => ['Which claims the token proves', 'PASS, FAIL or NOT PROVEN for each criterion you chose', 'Whether the token is valid, expired or revoked, and why a check failed', 'The exact value only if the graduate chose to reveal it'],
    'never' => ['The exact CGPA, courses or transcript', 'The graduate\'s wallet or credential file', 'Any claim the graduate left out of the token'],
    'notes' => [['Always current', 'Every check confirms the credential is still active and the token is unexpired and unrevoked. A revoked credential fails immediately.'], ['Cryptographically checked', 'The server verifies the zero-knowledge proof on every check. A tampered token fails.'], ['Hiring in volume?', 'Upload a list of tokens (CSV/XLSX), paste them, or scan many QR codes. Set the criteria once, watch the progress bar, then filter, sort and export the results to CSV or PDF. Consent-based background checks remain available from the dashboard.']],
    'cta' => ['Sign in as verifier', route('login')],
])
</x-layouts.public>
