<x-layouts.app title="Import result">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card"><p class="text-sm text-muted">Issued</p><p class="mt-1 font-display text-3xl font-bold">{{ $batch->issued_count }}</p></div>
        <div class="card"><p class="text-sm text-muted">Failed</p><p class="mt-1 font-display text-3xl font-bold {{ $batch->failed_count ? 'text-bad' : '' }}">{{ $batch->failed_count }}</p></div>
        <div class="card"><p class="text-sm text-muted">Batch</p><p class="mt-1 break-all font-mono text-sm">{{ $batch->batch_id }}</p>
            <p class="mt-1 break-all font-mono text-[10px] text-muted" title="sha256 over the sorted commitments of this batch">digest {{ $batch->batch_digest }}</p></div>
    </div>
    <p class="mt-4 text-sm text-muted">Each credential was sealed to its student's wallet. Students receive it when they open the wallet.</p>
    <div class="mt-4 flex gap-3">
        @if($batch->failed_count)<a class="btn-primary" href="{{ route('admin.import.errors', $batch->batch_id) }}">Download error report (CSV)</a>@endif
        <a class="btn-ghost" href="{{ route('admin.credentials') }}">View credentials</a>
        <a class="btn-ghost" href="{{ route('admin.import') }}">Import another file</a>
    </div>
    @if($batch->failed_count)
    <div class="card mt-6 overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Row</th><th class="px-4 py-2">Student</th><th class="px-4 py-2">Errors</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @foreach($batch->errors as $e)<tr><td class="px-4 py-2">{{ $e['row'] }}</td><td class="px-4 py-2 font-mono">{{ $e['student_id'] }}</td><td class="px-4 py-2 text-bad">{{ implode('; ', $e['errors']) }}</td></tr>@endforeach
            </tbody>
        </table>
    </div>
    @endif
</x-layouts.app>
