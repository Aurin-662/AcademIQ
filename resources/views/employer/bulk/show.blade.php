@php
    $done = $b->status === 'done';
    $tone = ['PASS' => 'text-ok', 'FAIL' => 'text-bad', 'NOT PROVEN' => 'text-warn', 'INVALID' => 'text-bad', 'DUPLICATE' => 'text-muted'];
    $link = fn (array $q) => request()->fullUrlWithQuery($q);
    $th = function ($label, $col) use ($sort, $dir, $link) {
        $next = $sort === $col && $dir === 'asc' ? 'desc' : 'asc';
        $mark = $sort === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
        return '<a class="hover:text-navy" href="'.e($link(['sort' => $col, 'dir' => $next])).'">'.e($label).$mark.'</a>';
    };
    $names = ['all' => 'All', 'passed' => 'Passed', 'failed' => 'Failed', 'not_proven' => 'Not proven', 'invalid' => 'Invalid', 'duplicate' => 'Duplicates'];
@endphp
<x-layouts.app :title="'Batch '.$b->batch_id" :subtitle="$done ? 'Finished '.$b->finished_at?->format('Y-m-d H:i').' UTC.' : 'Processing. You can leave this page; progress is saved.'">
<x-stepper :steps="['Add tokens and criteria', 'Processing', 'Results']" :current="$done ? 3 : 2" />

@unless($done)
<div class="card" x-data="batchProgress({ p: @js($p), sync: @js($sync), stepUrl: '{{ route('employer.bulk.step', $b->batch_id) }}', progressUrl: '{{ route('employer.bulk.progress', $b->batch_id) }}' })">
    <div class="flex justify-between text-sm"><span>Checked <strong x-text="p.processed"></strong> of <strong x-text="p.total"></strong></span><span x-text="pct + '%'"></span></div>
    <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-soft" role="progressbar" :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100"><div class="h-full bg-kuet transition-all" :style="'width:' + pct + '%'"></div></div>
    <p x-show="err" x-cloak class="mt-3 text-sm text-warn" role="status" x-text="err"></p>
</div>
@else
<div class="mb-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <div class="card !p-4"><p class="text-xs text-muted">Tokens</p><p class="font-display text-2xl font-semibold">{{ $p['total'] }}</p></div>
    @foreach($p['counts'] as $k => $n)<div class="card !p-4"><p class="text-xs text-muted">{{ ucfirst(strtolower($k)) }}</p><p class="font-display text-2xl font-semibold {{ $tone[$k] }}">{{ $n }}</p></div>@endforeach
</div>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
    <nav class="flex flex-wrap gap-1.5" aria-label="Filter results">
        @foreach($filters as $f)<a href="{{ $link(['filter' => $f]) }}" @if($filter === $f) aria-current="true" @endif class="rounded-full border px-3 py-1 text-sm {{ $filter === $f ? 'border-navy bg-navy text-white' : 'border-slate-300 bg-white text-muted hover:text-navy' }}">{{ $names[$f] }}</a>@endforeach
    </nav>
    <div class="flex gap-2"><a class="btn-ghost" href="{{ route('employer.bulk.csv', $b->batch_id) }}">Export CSV</a><a class="btn-primary" href="{{ route('employer.bulk.pdf', $b->batch_id) }}">Export PDF</a></div>
</div>

<div class="card overflow-x-auto !p-0">
    <table class="tbl w-full text-left text-sm">
        <thead><tr>
            <th>{!! $th('Row', 'row') !!}</th><th>{!! $th('Token', 'token') !!}</th><th>{!! $th('Candidate', 'candidate') !!}</th>
            @foreach($b->criteria as $c)<th>{{ $c['label'] }}</th>@endforeach
            <th>{!! $th('Overall', 'overall') !!}</th><th>Reason</th>
        </tr></thead>
        <tbody>
        @forelse($items as $i)
            @php($by = array_column($i->results ?? [], 'result', 'key'))
            <tr>
                <td class="text-muted">{{ $i->row }}</td>
                <td class="font-mono text-xs">{{ $i->token_id ?? $i->input }}</td>
                <td>{{ $i->candidate_name }}@if($i->cv_ref)<span class="block text-xs text-muted">{{ $i->cv_ref }}</span>@endif</td>
                @foreach($b->criteria as $c)<td class="{{ $tone[$by[$c['key']] ?? ''] ?? 'text-muted' }}">{{ $by[$c['key']] ?? '·' }}</td>@endforeach
                <td class="font-semibold {{ $tone[$i->overall] ?? '' }}">{{ $i->overall }}</td>
                <td class="text-xs text-muted">{{ \App\Services\Verification\BatchVerifier::reasonText($i->reason) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 5 + count($b->criteria) }}" class="text-muted">No rows match this filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="mt-3 text-xs text-muted">Outcomes only; hidden values are never available. Each token was checked as a single verification would be (token status, credential current, issuer valid, proof verified) and is recorded in the token's usage log.</p>
@endunless
</x-layouts.app>
