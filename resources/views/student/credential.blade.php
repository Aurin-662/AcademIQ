<x-layouts.app :title="$credential->credential_id">
    <div class="card grid gap-3 text-sm sm:grid-cols-2">
        <p><span class="text-muted">Status</span><br><x-status :value="$meta['status']" /></p>
        <p><span class="text-muted">Version</span><br>v{{ $meta['version'] }}</p>
        <p><span class="text-muted">Issued</span><br>{{ $meta['issue_date'] }}</p>
        <p><span class="text-muted">Issuer</span><br>{{ $meta['issuer'] }}</p>
        <p class="sm:col-span-2"><span class="text-muted">Commitment reference</span><br><span class="break-all font-mono text-xs">{{ $meta['commitment'] }}</span></p>
        @if($meta['status'] === 'revoked')<p class="text-bad sm:col-span-2">Revoked: {{ $meta['revocation_reason'] }}</p>@endif
    </div>
    <p class="mt-4 text-sm text-muted">Your private academic details are held only in your wallet. <a class="underline" href="{{ route('student.wallet.index') }}">Open wallet</a> to download the package or back it up.</p>
</x-layouts.app>
