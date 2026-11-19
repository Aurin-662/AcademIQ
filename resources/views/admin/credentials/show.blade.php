<x-layouts.app :title="$credential->credential_id">
    @error('credential')<div class="mb-4 rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad">{{ $message }}</div>@enderror
    <div class="card grid gap-3 text-sm sm:grid-cols-2">
        <p><span class="text-muted">Student</span><br>{{ $credential->student_reference }}</p>
        <p><span class="text-muted">Status</span><br><x-status :value="$credential->credential_status" /></p>
        <p><span class="text-muted">Issuer</span><br>{{ $credential->issuer_id }} · key {{ $credential->issuer_key_id }}</p>
        <p><span class="text-muted">Issued / current version</span><br>{{ $credential->issue_date }} · v{{ $credential->current_version }}</p>
        <p class="sm:col-span-2"><span class="text-muted">Commitment (public)</span><br><span class="break-all font-mono text-xs">{{ $credential->commitment }}</span></p>
        @if($credential->credential_status === 'revoked')<p class="sm:col-span-2 text-bad">Revoked: {{ $credential->revocation_reason }}</p>@endif
    </div>
    <p class="mt-3 text-xs text-muted">Private academic values are not stored by the server and are not shown here.</p>

    <h2 class="mb-2 mt-8 text-lg font-bold">Version history</h2>
    <div class="card divide-y divide-slate-soft !p-0">
        @foreach($versions as $v)<div class="flex items-center justify-between gap-4 px-4 py-3 text-sm"><span>v{{ $v->version }} · {{ $v->issue_date }}</span><span class="hidden truncate font-mono text-xs text-muted sm:inline">{{ \Illuminate\Support\Str::limit($v->commitment, 24) }}</span><x-status :value="$v->status" /></div>@endforeach
    </div>

    @if($credential->credential_status === 'active')
    <div class="mt-8 flex flex-wrap items-start gap-4">
        <a href="{{ route('admin.credentials.edit', $credential->credential_id) }}" class="btn-ghost">Issue new version</a>
        <form method="POST" action="{{ route('admin.credentials.revoke', $credential->credential_id) }}" class="card flex-1 space-y-3" onsubmit="return confirm('Revoke this credential? This cannot be undone.')">
            @csrf
            <label class="label" for="reason">Revoke credential: reason</label>
            <input class="input" id="reason" name="reason" required minlength="5" maxlength="500">
            @error('reason')<p class="text-sm text-bad">{{ $message }}</p>@enderror
            <button class="btn-danger" type="submit">Revoke</button>
        </form>
    </div>
    @endif
</x-layouts.app>
