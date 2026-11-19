<x-layouts.app title="Verification keys">
    <p class="mb-4 max-w-2xl text-sm text-muted">Groth16 verification keys, one per circuit version. These are not issuer signing keys: issuer keys authenticate credentials, verification keys check proofs. Retiring a version makes the server reject proofs made under it.</p>
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Circuit</th><th class="px-4 py-2">System</th><th class="px-4 py-2">Fingerprint (SHA-256)</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($keys as $k)
                <tr><td class="px-4 py-2">{{ $k->circuit_id }} v{{ $k->circuit_version }}</td><td class="px-4 py-2">{{ $k->proof_system }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ \Illuminate\Support\Str::limit($k->sha256, 20) }}</td>
                    <td class="px-4 py-2"><x-status :value="$k->status" /></td>
                    <td class="px-4 py-2 text-right"><form method="POST" action="{{ route('admin.keys.toggle', $k) }}" onsubmit="return confirm('Change status of this verification key?')">@csrf<button class="btn-ghost !py-1">{{ $k->status === 'active' ? 'Retire' : 'Activate' }}</button></form></td></tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-muted">No verification key registered. Run <code>php artisan zkp:register credential 1</code>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
