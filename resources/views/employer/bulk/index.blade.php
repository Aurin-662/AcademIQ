<x-layouts.app title="Bulk verification" :subtitle="'Check many candidates against the same criteria. Up to '.$max.' tokens per batch.'">
<x-stepper :steps="['Add tokens and criteria', 'Processing', 'Results']" :current="1" />
<form method="POST" action="{{ route('employer.bulk.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-2" x-data="qrscan({ collect: true })" @beforeunload.window="stop()">
    @csrf
    <div class="space-y-6">
        <section class="card space-y-4">
            <h2 class="text-lg">1. Tokens</h2>
            @error('tokens')<p class="rounded-md border border-bad/30 bg-bad/10 p-3 text-sm text-bad" role="alert">{{ $message }}</p>@enderror
            <div><label class="label" for="file">Upload CSV or XLSX</label>
                <input class="input" id="file" name="file" type="file" accept=".csv,.xlsx">
                <p class="mt-1 text-xs text-muted">Needs a <code>token_id</code> column. Optional: <code>candidate_name</code>, <code>cv_ref</code>.</p>
                @error('file')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="tokens">Or paste tokens</label>
                <textarea class="input font-mono" id="tokens" name="tokens" rows="5" placeholder="ACQ-XXXX-XXXX-XXXX-XXXX, Candidate name, CV ref&#10;one per line">{{ old('tokens') }}</textarea></div>
            <div class="space-y-3 border-t border-slate-soft pt-4">
                <p class="label !mb-0">Or scan QR codes</p>
                <p x-show="err" x-cloak class="text-sm text-bad" role="alert" x-text="err"></p>
                <video x-ref="video" x-show="on" x-cloak class="w-full rounded-md bg-navy" playsinline muted aria-label="Camera preview"></video>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-ghost" x-show="!on" @click="start()">Start camera</button>
                    <button type="button" class="btn-ghost" x-show="on" x-cloak @click="stop()">Stop camera</button>
                    <label class="btn-ghost cursor-pointer">Upload QR images<input class="sr-only" type="file" accept="image/*" multiple @change="scanFile($event)"></label>
                    <button type="button" class="btn-ghost" x-show="found.length" x-cloak @click="found = []">Clear scans</button>
                </div>
                <p class="text-sm text-muted"><span x-text="found.length"></span> scanned this session. Duplicates are ignored.</p>
                <textarea name="scanned" class="sr-only" tabindex="-1" aria-hidden="true" x-text="found.join('\n')"></textarea>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="card space-y-4">
            <h2 class="text-lg">2. Criteria (applied to every token)</h2>
            <p class="text-sm text-muted">Every criterion you fill in must pass. A token that did not include that claim shows NOT PROVEN for it.</p>
            @error('criteria')<p class="text-sm text-bad">{{ $message }}</p>@enderror
            <div><label class="label" for="cgpa_min">CGPA at least</label>
                <select class="input max-w-xs" id="cgpa_min" name="cgpa_min"><option value="">Any</option>@foreach($ladder as $v)<option value="{{ $v }}" @selected(old('cgpa_min') === $v)>{{ $v }}</option>@endforeach</select>
                @error('cgpa_min')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            <div><span class="label">Passing year</span>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <input class="input w-28" name="year_from" type="number" min="{{ $minYear }}" max="{{ $maxYear }}" placeholder="From" value="{{ old('year_from') }}" aria-label="Passing year from">
                    <span>to</span>
                    <input class="input w-28" name="year_to" type="number" min="{{ $minYear }}" max="{{ $maxYear }}" placeholder="To" value="{{ old('year_to') }}" aria-label="Passing year to">
                </div>
                <p class="mt-1 text-xs text-muted">Same year in both for exact. Only "From" means that year or later.</p>
                @error('year_to')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            @foreach(['institution' => ['Institution', 'institutions'], 'degree' => ['Degree', 'degrees'], 'department' => ['Department', 'programmes']] as $k => [$label, $table])
            <div><label class="label" for="{{ $k }}">{{ $label }}</label>
                <select class="input max-w-md" id="{{ $k }}" name="{{ $k }}"><option value="">Any</option>@foreach(config("academiq.$table") as $code => $name)<option value="{{ $code }}" @selected((string) old($k) === (string) $code)>{{ $name }}</option>@endforeach</select></div>
            @endforeach
            <button class="btn-primary" type="submit">Start verification</button>
        </section>
    </div>
</form>

<h2 class="mb-3 mt-10 text-lg">Recent batches</h2>
<div class="card overflow-x-auto !p-0">
    <table class="tbl w-full text-left text-sm">
        <thead><tr><th>Batch</th><th>Created</th><th>Tokens</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($recent as $r)
            <tr><td class="font-mono"><a class="underline" href="{{ route('employer.bulk.show', $r->batch_id) }}">{{ $r->batch_id }}</a></td><td>{{ $r->created_at?->format('Y-m-d H:i') }}</td><td>{{ $r->total }}</td><td><x-status :value="$r->status === 'done' ? 'verified' : 'pending'" /></td></tr>
        @empty
            <tr><td colspan="4" class="text-muted">No batches yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</x-layouts.app>
