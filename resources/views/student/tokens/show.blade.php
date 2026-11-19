@php($state = $t->status === 'revoked' ? 'revoked' : ($t->expires_at?->isPast() ? 'expired' : 'active'))
<x-layouts.app :title="'Token '.$t->token_id">
    <div class="max-w-2xl space-y-4" x-data="{ copied: '', copy(v, k) { navigator.clipboard.writeText(v).then(() => { this.copied = k; setTimeout(() => this.copied = '', 1500); }); } }">
        <div class="card space-y-3">
            <div class="flex items-center justify-between gap-3"><p class="font-mono text-xl font-bold">{{ $t->token_id }}</p><x-status :value="$state" /></div>
            <p class="break-all text-sm text-muted">{{ $link }}</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-ghost" @click="copy(@js($t->token_id), 'token')"><span x-text="copied === 'token' ? 'Copied' : 'Copy token'"></span></button>
                <button type="button" class="btn-ghost" @click="copy(@js($link), 'link')"><span x-text="copied === 'link' ? 'Copied' : 'Copy link'"></span></button>
            </div>
            <p class="text-sm text-muted">Expires {{ $t->expires_at?->format('Y-m-d H:i') }}. Created {{ $t->created_at?->format('Y-m-d H:i') }}.</p>
        </div>

        @if($state === 'active')
        <div class="card flex flex-wrap items-center gap-4" x-data="qrtools(@js(['link' => $link, 'token' => $t->token_id, 'claims' => $t->summaryLines(), 'expires' => $t->expires_at->format('Y-m-d')]))" x-init="init()">
            <img x-show="qr" :src="qr" alt="QR code that opens the verification link for this token" class="h-44 w-44 rounded-lg border border-slate-soft">
            <div class="space-y-2">
                <p class="text-sm text-muted">The QR contains only the verification link. Verifiers must sign in to use it.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-ghost" :disabled="!qr" @click="downloadQr()">Download QR (PNG)</button>
                    <button type="button" class="btn-ghost" :disabled="!qr" @click="downloadBadge()">Download CV badge</button>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <h2 class="font-bold">Claims in this token</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach($t->claims as $c)
                    <li>@switch($c['claim'])
                        @case('cgpa')CGPA: {{ $c['mode'] === 'reveal' ? 'exact value revealed' : 'threshold results (hidden value)' }}@break
                        @case('passing_year')Passing year between {{ $c['lo'] }} and {{ $c['hi'] }}@break
                        @default{{ ucfirst($c['claim']) }} equals {{ $c['label'] }}
                    @endswitch</li>
                @endforeach
            </ul>
        </div>

        @if($state === 'active')
        <form method="POST" action="{{ route('student.tokens.revoke', $t->token_id) }}" onsubmit="return confirm('Revoke this token? Verifiers will no longer be able to use it.')">@csrf
            <button class="btn-ghost" type="submit">Revoke token</button></form>
        @endif

        <div class="card !p-0">
            <h2 class="px-4 pt-4 font-bold">Usage log</h2>
            <div class="divide-y divide-slate-soft">
                @forelse($logs as $l)
                    <div class="flex justify-between gap-4 px-4 py-3 text-sm"><span>{{ $l->verifier_name ?? 'Verifier' }} · {{ $l->valid ? $l->overall : 'invalid ('.($l->reason ?? '').')' }}</span><span class="text-muted">{{ $l->created_at?->format('Y-m-d H:i') }}</span></div>
                @empty
                    <p class="px-4 py-6 text-sm text-muted">Nobody has verified this token yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
