<x-layouts.app title="Welcome, {{ auth()->user()->name }}">
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <a href="{{ route('student.wallet.index') }}" class="card block hover:border-teal"><p class="text-sm text-muted">Active credentials</p><p class="mt-1 font-display text-3xl font-bold">{{ $credentials }}</p><p class="mt-1 text-xs text-kuet">Open wallet</p></a>
        <a href="{{ route('student.requests.index') }}" class="card block hover:border-teal"><p class="text-sm text-muted">Awaiting your consent</p><p class="mt-1 font-display text-3xl font-bold">{{ $pending->count() }}</p><p class="mt-1 text-xs text-kuet">Review requests</p></a>
        <div class="card"><p class="text-sm text-muted">Proofs to generate</p><p class="mt-1 font-display text-3xl font-bold">{{ $proofs->count() }}</p></div>
    </div>

    @foreach($proofs as $r)
        <div class="card mb-3 flex items-center justify-between gap-3 text-sm"><span>Request <span class="font-mono">{{ $r->request_id }}</span> from {{ $r->company_name }} needs your proof.</span><a class="btn-primary !py-1.5" href="{{ route('student.proof.show', $r->request_id) }}">Generate proof</a></div>
    @endforeach
    @foreach($pending as $r)
        <div class="card mb-3 flex items-center justify-between gap-3 text-sm"><span>{{ $r->company_name }} is asking for your consent.</span><a class="btn-ghost !py-1.5" href="{{ route('student.requests.show', $r->request_id) }}">Review</a></div>
    @endforeach

    <h2 class="mb-2 mt-8 text-lg font-bold">Notifications</h2>
    <div class="card mb-2 divide-y divide-slate-soft !p-0">
        @forelse($notes as $n)
            <div class="px-4 py-3 text-sm {{ $n->read_at ? 'text-muted' : '' }}"><span class="{{ $n->read_at ? '' : 'font-semibold text-navy' }}">{{ $n->title }}</span> <span class="text-xs">· {{ $n->created_at?->diffForHumans() }}</span></div>
        @empty
            <p class="px-4 py-6 text-sm text-muted">No notifications yet.</p>
        @endforelse
    </div>
    <a href="{{ route('notifications') }}" class="text-sm text-kuet underline">All notifications</a>

    <h2 class="mb-2 mt-8 text-lg font-bold">Recent verification history</h2>
    <div class="card divide-y divide-slate-soft !p-0">
        @forelse($history as $r)
            <a href="{{ route('student.requests.show', $r->request_id) }}" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-soft"><span>{{ $r->company_name }} · {{ $r->created_at?->format('Y-m-d') }}</span><x-status :value="$r->request_status" /></a>
        @empty
            <p class="px-4 py-6 text-sm text-muted">No verification history yet.</p>
        @endforelse
    </div>
</x-layouts.app>
