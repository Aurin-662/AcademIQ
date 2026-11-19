<x-layouts.public title="How it works">
<x-page-header title="How it works" lead="From issuance to an outcomes-only report." />
<section class="mx-auto max-w-4xl space-y-4 px-4 py-14 sm:px-6">
    @foreach([
        ['1. Issuance', 'An authorized issuer enters the credential. The server builds a canonical payload, a random salt, a Poseidon commitment and an EdDSA signature, registers only the commitment and status, and hands the full package to the student once.'],
        ['2. Request', 'An employer submits a request for a student reference with chosen claims and checks.'],
        ['3. Consent', 'The student reviews employer, claims, checks and data shared, then approves or rejects.'],
        ['4. Proof', 'The browser generates a Groth16 proof. The server verifies it against the verification key and current credential status.'],
        ['5. Background checks', 'With consent, tasks go to the previous employer, referee or police verifier portals.'],
        ['6. Report', 'Results are consolidated into a PDF with outcomes only. Every access is logged.'],
    ] as [$t, $d])
    <div class="card"><h2 class="text-lg font-bold">{{ $t }}</h2><p class="mt-2 text-sm text-muted">{{ $d }}</p></div>
    @endforeach
</section>
</x-layouts.public>
