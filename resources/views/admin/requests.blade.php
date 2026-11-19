<x-layouts.app title="Request and consent log">
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Request</th><th class="px-4 py-2">Employer</th><th class="px-4 py-2">Applicant</th><th class="px-4 py-2">Requested</th><th class="px-4 py-2">Consent</th><th class="px-4 py-2">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @forelse($requests as $r)
                @php($c = $consents[$r->request_id] ?? null)
                <tr>
                    <td class="px-4 py-2 font-mono text-xs">{{ $r->request_id }}<br><span class="text-muted">{{ $r->created_at?->format('Y-m-d H:i') }}</span></td>
                    <td class="px-4 py-2">{{ $r->company_name }}</td><td class="px-4 py-2">{{ $r->student_reference }}</td>
                    <td class="px-4 py-2 text-xs">{{ implode(', ', array_keys(app(\App\Services\Verification\GatewayService::class)->scopeOf($r))) }}</td>
                    <td class="px-4 py-2 text-xs">@if($c){{ $c->decision }} · {{ $c->decision_timestamp?->format('Y-m-d H:i') }}<br>{{ $c->authorization_reference }}@if($c->revoked_at) · withdrawn @endif @else pending @endif</td>
                    <td class="px-4 py-2"><x-status :value="$r->request_status" /></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-muted">No requests.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.app>
