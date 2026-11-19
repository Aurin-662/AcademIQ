<x-layouts.app title="Verification requests">
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Request</th><th class="px-4 py-2">Employer</th><th class="px-4 py-2">Received</th><th class="px-4 py-2">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($requests as $r)
                <tr><td class="px-4 py-2 font-mono"><a class="underline" href="{{ route('student.requests.show', $r->request_id) }}">{{ $r->request_id }}</a></td><td class="px-4 py-2">{{ $r->company_name }}</td>
                    <td class="px-4 py-2">{{ $r->created_at?->format('Y-m-d') }}</td><td class="px-4 py-2"><x-status :value="$r->request_status" /></td></tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-muted">No requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
