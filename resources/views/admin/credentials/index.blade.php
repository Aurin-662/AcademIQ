<x-layouts.app title="Credentials">
    <div class="mb-4 flex gap-3"><a href="{{ route('admin.credentials.create') }}" class="btn-primary">Issue credential</a><a href="{{ route('admin.import') }}" class="btn-ghost">Bulk import / revoke</a></div>
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Credential</th><th class="px-4 py-2">Student</th><th class="px-4 py-2">Issued</th><th class="px-4 py-2">Ver.</th><th class="px-4 py-2">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($credentials as $c)
                <tr><td class="px-4 py-2 font-mono"><a class="underline" href="{{ route('admin.credentials.show', $c->credential_id) }}">{{ $c->credential_id }}</a></td>
                    <td class="px-4 py-2">{{ $c->student_reference }}</td><td class="px-4 py-2">{{ $c->issue_date }}</td><td class="px-4 py-2">{{ $c->current_version }}</td>
                    <td class="px-4 py-2"><x-status :value="$c->credential_status" /></td></tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-muted">No credentials issued yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $credentials->links() }}</div>
</x-layouts.app>
