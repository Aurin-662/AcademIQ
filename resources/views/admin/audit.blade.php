<x-layouts.app title="Audit log">
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div><label class="label" for="group">Event group</label><select class="input" id="group" name="group"><option value="">All</option>@foreach($groups as $g)<option value="{{ $g }}" @selected(($filters['group'] ?? '') === $g)>{{ $g }}</option>@endforeach</select></div>
        <div><label class="label" for="ref">Entity reference</label><input class="input" id="ref" name="ref" value="{{ $filters['ref'] ?? '' }}" placeholder="REQ-… / RPT-… / KUET-CRED-…"></div>
        <button class="btn-ghost" type="submit">Filter</button>
    </form>
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Time</th><th class="px-4 py-2">Action</th><th class="px-4 py-2">Role</th><th class="px-4 py-2">Entity</th><th class="px-4 py-2">IP</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($logs as $l)
                <tr><td class="whitespace-nowrap px-4 py-2">{{ $l->timestamp?->format('Y-m-d H:i:s') }}</td><td class="px-4 py-2 font-medium">{{ $l->action }}</td><td class="px-4 py-2">{{ $l->actor_role ?? '-' }}</td><td class="px-4 py-2">{{ $l->entity_type }} {{ $l->entity_reference }}</td><td class="px-4 py-2">{{ $l->ip }}</td></tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-muted">No records.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
