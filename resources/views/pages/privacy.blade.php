<x-layouts.public title="Privacy and security">
<x-page-header title="Privacy and security" lead="Private inputs stay on the holder side." />
<section class="mx-auto max-w-4xl space-y-6 px-4 py-14 sm:px-6">
    <div class="card"><ul class="list-disc space-y-2 pl-5 text-muted">
        <li>Credential data, salt and issuer signature never reach the server during proving or verification.</li>
        <li>The credential store holds commitments, status and metadata only.</li>
        <li>Employers receive outcomes, never credentials, signatures, salts or proofs.</li>
        <li>Consent is checked before any verification or background routing.</li>
        <li>Issuer signing keys are kept outside the database and never in browser code.</li>
        <li>Audit records are append-only and access-restricted.</li>
    </ul></div>
    <div class="card"><h2 class="text-lg font-bold">Wallet backup</h2><p class="mt-2 text-sm text-muted">The wallet is encrypted on your device. Keep an exported backup and your passphrase safe; if both are lost, ask KUET to reissue the credential.</p></div>
</section>
</x-layouts.public>
