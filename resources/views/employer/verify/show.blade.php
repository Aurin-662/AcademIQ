<x-layouts.app title="Verify token">
<div class="max-w-2xl space-y-4">
    <div class="card"><p class="text-sm text-muted">Token</p><p class="font-mono text-xl font-bold">{{ $tokenId }}</p></div>

    @if($problem)
        <div class="rounded-lg border border-bad/30 bg-bad/10 p-4 text-sm text-bad" role="alert">{{ $problemText }} This token cannot be used.</div>
        <a class="btn-ghost" href="{{ route('employer.verify.index') }}">Check another token</a>
    @else
        @php($claims = collect($t->claims)->keyBy('claim'))
        <div class="card">
            <h2 class="font-bold">What this token proves</h2>
            <ul class="mt-2 list-disc pl-5 text-sm">@foreach($t->summaryLines() as $line)<li>{{ $line }}</li>@endforeach</ul>
            <p class="mt-2 text-sm text-muted">Expires {{ $t->expires_at->format('Y-m-d') }}. Hidden values are never shown to you.</p>
        </div>

        <form method="POST" action="{{ route('employer.verify.check', $tokenId) }}" class="card space-y-4">
            @csrf
            <h2 class="text-lg font-bold">Criteria</h2>
            <p class="text-sm text-muted">Every criterion you fill in must pass. Leave a field empty to skip it. Only claims in this token can be checked.</p>
            @error('criteria')<p class="text-sm text-bad">{{ $message }}</p>@enderror

            @if($claims->has('cgpa'))
            <div><label class="label" for="cgpa_min">CGPA at least</label>
                <select class="input max-w-xs" id="cgpa_min" name="cgpa_min"><option value="">Any</option>@foreach($ladder as $v)<option value="{{ $v }}" @selected(old('cgpa_min') === $v)>{{ $v }}</option>@endforeach</select></div>
            @endif
            @if($claims->has('passing_year'))
            <div><span class="label">Passing year</span>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <input class="input w-28" name="year_from" type="number" min="{{ $minYear }}" max="{{ $maxYear }}" placeholder="From" value="{{ old('year_from') }}" aria-label="Passing year from">
                    <span>to</span>
                    <input class="input w-28" name="year_to" type="number" min="{{ $minYear }}" max="{{ $maxYear }}" placeholder="To" value="{{ old('year_to') }}" aria-label="Passing year to">
                    <span class="text-muted">Same year in both for exact. Only "From" means that year or later.</span></div>
                @error('year_to')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            @endif
            @foreach(['institution' => ['Institution', 'institutions'], 'degree' => ['Degree', 'degrees'], 'department' => ['Department', 'programmes']] as $k => [$label, $table])
                @if($claims->has($k))
                <div><label class="label" for="{{ $k }}">{{ $label }}</label>
                    <select class="input max-w-md" id="{{ $k }}" name="{{ $k }}"><option value="">Any</option>@foreach(config("academiq.$table") as $code => $name)<option value="{{ $code }}" @selected((string) old($k) === (string) $code)>{{ $name }}</option>@endforeach</select></div>
                @endif
            @endforeach
            <button class="btn-primary" type="submit">Verify</button>
        </form>
    @endif
</div>
</x-layouts.app>
