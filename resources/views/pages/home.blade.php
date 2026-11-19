<x-layouts.public>
<section class="bg-gradient-to-br from-navy via-navy to-kuet text-white">
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-20 sm:px-6 md:grid-cols-2 fade-in">
        <div>
            <p class="mb-3 text-sm font-semibold text-teal">KUET · Academic Credential Verification</p>
            <h1 class="text-4xl font-extrabold leading-tight !text-white sm:text-5xl">Trust Academic Credentials. Verify Without Revealing.</h1>
            <p class="mt-5 max-w-xl text-lg text-slate-200">Students hold their KUET credentials in a private wallet and prove specific claims to employers with Zero-Knowledge Proofs. No transcripts handed over, no raw data exposed.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('how-it-works') }}" class="btn bg-teal text-navy hover:bg-white">See how it works</a>
                <a href="{{ route('privacy') }}" class="btn border border-white/30 text-white hover:bg-white/10">Privacy &amp; security</a>
            </div>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/5 p-6">
            <p class="text-sm text-slate-300">Example claim an employer can verify</p>
            <p class="mt-2 font-display text-2xl font-bold">“CGPA ≥ 3.00”</p>
            <ul class="mt-5 space-y-3 text-sm">
                <li class="flex gap-2"><span class="text-teal">✓</span> Issued by an authorized KUET issuer</li>
                <li class="flex gap-2"><span class="text-teal">✓</span> Credential active and not revoked</li>
                <li class="flex gap-2"><span class="text-teal">✓</span> Student consented to this exact check</li>
                <li class="flex gap-2"><span class="text-slate-400">✕</span> <span class="text-slate-300">Actual CGPA, courses, signature: never shared</span></li>
            </ul>
        </div>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <h2 class="text-2xl font-bold">How verification works</h2>
    <ol class="mt-8 grid gap-4 md:grid-cols-4">
        @foreach([['Issue','An authorized KUET issuer signs a credential. Only its commitment and status are registered.'],['Hold','The student keeps the full package in an encrypted wallet on their own device.'],['Consent','An employer requests checks. The student sees the exact scope and approves or rejects.'],['Prove','The browser generates a proof. The server verifies it and the employer gets an outcomes-only report.']] as $i => [$t, $d])
        <li class="card"><span class="badge bg-slate-soft text-kuet">Step {{ $i + 1 }}</span><h3 class="mt-3 text-lg font-bold">{{ $t }}</h3><p class="mt-2 text-sm text-muted">{{ $d }}</p></li>
        @endforeach
    </ol>
</section>

<section class="bg-white">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-2">
        <div>
            <h2 class="text-2xl font-bold">Zero-Knowledge Proofs, plainly</h2>
            <p class="mt-3 text-muted">A Zero-Knowledge Proof lets you show a statement is true without showing why. AcademiQ uses Groth16 proofs built with Circom and SnarkJS. The proof is generated in the student's browser, and the server only sees the proof and public values.</p>
            <p class="mt-3 text-muted">Supported claims are a fixed, documented set: CGPA at or above a threshold, programme match, degree category, and authorized issuer.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="card"><h3 class="font-bold">For students</h3><p class="mt-2 text-sm text-muted">Control who sees what. Every request needs your explicit, scoped consent.</p></div>
            <div class="card"><h3 class="font-bold">For employers</h3><p class="mt-2 text-sm text-muted">Cryptographic assurance instead of paper copies, plus consented background checks.</p></div>
            <div class="card"><h3 class="font-bold">Privacy-first</h3><p class="mt-2 text-sm text-muted">Private inputs never leave the holder device. Reports contain outcomes only.</p></div>
            <div class="card"><h3 class="font-bold">Auditable</h3><p class="mt-2 text-sm text-muted">Issuance, consent, verification and report access are recorded in an append-only log.</p></div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
    <h2 class="text-2xl font-bold">FAQ</h2>
    <div class="mt-6 divide-y divide-slate-soft rounded-xl border border-slate-soft bg-white" x-data="{ o: null }">
        @foreach([['Does the server ever see my transcript?','No. Credential data, salt and issuer signature stay in your wallet. The server receives only the proof and public inputs.'],['What if I lose my wallet?','Private data is intentionally not stored on the server, so it cannot be silently reconstructed. Keep an encrypted backup; KUET can reissue a credential.'],['Can an employer see my result without consent?','No. Nothing proceeds until you approve the exact scope.'],['Are police and employment checks automatic?','No. They are routed to authorized participants who respond manually through restricted portals.']] as $i => [$q, $a])
        <div class="p-4">
            <button class="flex w-full items-center justify-between text-left font-semibold text-navy" @click="o = o === {{ $i }} ? null : {{ $i }}" :aria-expanded="o === {{ $i }}">{{ $q }}<span x-text="o === {{ $i }} ? '−' : '+'" aria-hidden="true"></span></button>
            <p x-show="o === {{ $i }}" x-cloak x-transition class="mt-2 text-sm text-muted">{{ $a }}</p>
        </div>
        @endforeach
    </div>
</section>
</x-layouts.public>
