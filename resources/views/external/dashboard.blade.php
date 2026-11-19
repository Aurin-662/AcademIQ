<x-layouts.app title="My verification tasks">
    <p class="mb-4 rounded-lg bg-warn/15 p-3 text-sm text-ink">Responses on AcademiQ are entered manually by authorized participants. This portal is not connected to any police or government database.</p>
    <h2 class="mb-2 text-lg font-bold">Awaiting your response</h2>
    <div class="card mb-8 divide-y divide-slate-soft !p-0">
        @forelse($open as $t)
            <a href="{{ route('external.task', $t->getKey()) }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-slate-soft"><span><span class="font-medium">{{ \App\Services\Verification\ClaimDescriber::backgroundLabel($t->verification_type) }}</span> · {{ $t->details['applicant_name'] }} · due {{ $t->due_at->format('Y-m-d') }}</span><x-status value="pending" /></a>
        @empty
            <p class="px-4 py-6 text-sm text-muted">Nothing waiting for you.</p>
        @endforelse
    </div>
    <h2 class="mb-2 text-lg font-bold">Response history</h2>
    <div class="card divide-y divide-slate-soft !p-0">
        @forelse($done as $t)
            <a href="{{ route('external.task', $t->getKey()) }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-slate-soft"><span>{{ $t->details['applicant_name'] }} · {{ $t->response_timestamp?->format('Y-m-d') ?? '-' }}</span><x-status :value="$t->status" /></a>
        @empty
            <p class="px-4 py-6 text-sm text-muted">No responses yet.</p>
        @endforelse
    </div>
</x-layouts.app>
