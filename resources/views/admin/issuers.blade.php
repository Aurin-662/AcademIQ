<x-layouts.app title="Authorized issuers">
    <form method="POST" action="{{ route('admin.issuers.store') }}" class="card mb-8 grid gap-4 sm:grid-cols-3">
        @csrf
        <h2 class="text-lg font-bold sm:col-span-3">Authorize an issuer</h2>
        <div><label class="label" for="user_id">Account</label>
            <select class="input" id="user_id" name="user_id" required>@foreach($candidates as $c)<option value="{{ $c->getKey() }}">{{ $c->name }} ({{ $c->role }})</option>@endforeach</select>
            @error('user_id')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        <x-field name="name" label="Name on credentials" />
        <x-field name="designation" label="Designation" />
        <div class="sm:col-span-3"><button class="btn-primary" type="submit">Authorize and generate signing key</button></div>
    </form>
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Issuer</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Current key</th><th class="px-4 py-2"></th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($issuers as $i)
                <tr><td class="px-4 py-2">{{ $i->name }}<br><span class="text-xs text-muted">{{ $i->designation }} · {{ $i->issuer_id }}</span></td>
                    <td class="px-4 py-2"><x-status :value="$i->authorization_status" /></td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $i->current_key_id }}<br><span class="text-muted">{{ count($i->keys) }} key(s) total</span></td>
                    <td class="flex justify-end gap-2 px-4 py-2">
                        <form method="POST" action="{{ route('admin.issuers.toggle', $i) }}">@csrf<button class="btn-ghost !py-1">{{ $i->authorization_status === 'active' ? 'Suspend' : 'Reactivate' }}</button></form>
                        <form method="POST" action="{{ route('admin.issuers.rotate', $i) }}" onsubmit="return confirm('Rotate the signing key?')">@csrf<button class="btn-ghost !py-1">Rotate key</button></form>
                    </td></tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-muted">No issuers yet. Authorize an admin or issuer account above.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
