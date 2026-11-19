<x-layouts.app title="Registered students">
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Student reference</th><th class="px-4 py-2">Account</th><th class="px-4 py-2">Wallet</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($students as $s)
                <tr><td class="px-4 py-2 font-mono">{{ $s->student_reference }}</td><td class="px-4 py-2"><x-status :value="$s->account_status" /></td>
                    <td class="px-4 py-2">{{ $s->wallet_public_key ? 'Ready' : 'Not set up' }}</td></tr>
            @empty
                <tr><td colspan="3" class="px-4 py-6 text-muted">No students registered.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $students->links() }}</div>
</x-layouts.app>
