<x-layouts.app title="Employer dashboard">
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="card"><p class="text-sm text-muted">Open requests</p><p class="mt-1 font-display text-3xl font-bold">{{ $counts['pending'] }}</p></div>
        <div class="card"><p class="text-sm text-muted">Completed</p><p class="mt-1 font-display text-3xl font-bold">{{ $counts['completed'] }}</p></div>
        <div class="card flex items-center"><a href="{{ route('employer.requests.create') }}" class="btn-primary w-full">New verification request</a></div>
    </div>
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Request</th><th class="px-4 py-2">Applicant ref.</th><th class="px-4 py-2">Submitted</th><th class="px-4 py-2">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($requests as $r)
                <tr><td class="px-4 py-2 font-mono"><a class="underline" href="{{ route('employer.requests.show', $r->request_id) }}">{{ $r->request_id }}</a></td><td class="px-4 py-2">{{ $r->student_reference }}</td>
                    <td class="px-4 py-2">{{ $r->created_at?->format('Y-m-d') }}</td><td class="px-4 py-2"><x-status :value="$r->request_status" /></td></tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-muted">No requests yet. Create one to verify an applicant.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
