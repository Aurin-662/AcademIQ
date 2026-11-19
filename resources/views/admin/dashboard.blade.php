<x-layouts.app title="Admin overview">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($stats as $label => $n)
            <div class="card"><p class="text-sm text-muted">{{ $label }}</p><p class="mt-1 font-display text-3xl font-bold">{{ $n }}</p></div>
        @endforeach
    </div>

    <h2 class="mb-3 mt-8 text-lg font-bold">Credentials issued, last 14 days</h2>
    @php($max = max(1, $activity->max('count')))
    <div class="card">
        <div class="flex h-32 items-end gap-1.5" role="img" aria-label="Bar chart of credentials issued per day for the last 14 days">
            @foreach($activity as $a)
                <div class="flex h-full flex-1 flex-col items-center justify-end" title="{{ $a['day'] }}: {{ $a['count'] }}">
                    <span class="mb-1 text-[10px] text-muted">{{ $a['count'] ?: '' }}</span>
                    <div class="w-full rounded-t bg-teal" style="height: {{ max(2, $a['count'] / $max * 85) }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-[10px] text-muted"><span>{{ $activity->first()['day'] }}</span><span>{{ $activity->last()['day'] }}</span></div>
        @if($activity->sum('count') === 0)<p class="mt-2 text-sm text-muted">No credentials issued in this period.</p>@endif
    </div>

    <h2 class="mb-3 mt-8 text-lg font-bold">Recent activity</h2>
    <div class="card divide-y divide-slate-soft !p-0">
        @forelse($recent as $l)
            <div class="flex justify-between gap-4 px-4 py-3 text-sm"><span class="font-medium">{{ $l->action }}</span><span class="text-muted">{{ $l->timestamp?->format('Y-m-d H:i') }}</span></div>
        @empty
            <p class="px-4 py-6 text-sm text-muted">No activity yet.</p>
        @endforelse
    </div>
</x-layouts.app>
