<x-layouts.app title="Verification tokens">
    <div class="mb-4"><a href="{{ route('student.tokens.create') }}" class="btn-primary">Generate token</a></div>
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Token</th><th class="px-4 py-2">Claims</th><th class="px-4 py-2">Expires</th><th class="px-4 py-2">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($tokens as $t)
                @php($state = $t->status === 'revoked' ? 'revoked' : ($t->expires_at?->isPast() ? 'expired' : 'active'))
                <tr><td class="px-4 py-2 font-mono"><a class="underline" href="{{ route('student.tokens.show', $t->token_id) }}">{{ $t->token_id }}</a></td>
                    <td class="px-4 py-2">{{ collect($t->claims)->pluck('claim')->map(fn ($c) => str_replace('_', ' ', $c))->implode(', ') }}</td>
                    <td class="px-4 py-2">{{ $t->expires_at?->format('Y-m-d') }}</td>
                    <td class="px-4 py-2"><x-status :value="$state" /></td></tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-muted">No tokens yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
