<x-layouts.app :title="$credential ? 'Issue new version' : 'Issue credential'" :subtitle="$credential ? 'Corrected values produce a new signed version. The old version stops verifying.' : 'Look up the student, confirm the details, and issue. For many students use bulk import.'">
    @if($credential)<p class="mb-4 text-sm text-muted">Updating {{ $credential->credential_id }} for {{ $credential->student_reference }}. Enter the full corrected values; a new version, commitment and signature are generated.</p>@endif
    @unless($credential)<p class="mb-4 text-sm text-muted">Issuing many? <a class="underline" href="{{ route('admin.import') }}">Bulk import from CSV/XLSX</a>.</p>@endunless
    @error('credential')<div class="mb-4 rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad">{{ $message }}</div>@enderror
    @if($errors->has('student_reference') && $credential)<div class="mb-4 rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad">{{ $errors->first('student_reference') }}</div>@endif
    <form method="POST" action="{{ $credential ? route('admin.credentials.update', $credential->credential_id) : route('admin.credentials.store') }}" class="card grid gap-4 sm:grid-cols-2">
        @csrf
        @if($credential) @method('PUT') @endif
        <input type="hidden" name="idempotency_key" value="{{ $credential ? '' : (string) \Illuminate\Support\Str::uuid() }}">
        @unless($credential)
            <div x-data="studentLookup({ url: '{{ route('admin.students.lookup', '__REF__') }}' })">
                <x-field name="student_reference" label="Student reference" autocomplete="off" x-on:blur="check($event.target.value)" />
                <p class="mt-1 text-sm" :class="bad ? 'text-bad' : 'text-muted'" x-text="msg" aria-live="polite"></p>
            </div>
        @endunless
        <x-field name="cgpa" label="CGPA (0.00 to 4.00)" type="number" step="0.01" min="0" max="4" inputmode="decimal" />
        <div><label class="label" for="institution_code">Institution</label>
            <select class="input" id="institution_code" name="institution_code" required>@foreach(config('academiq.institutions') as $k => $v)<option value="{{ $k }}" @selected(old('institution_code') == $k)>{{ $v }}</option>@endforeach</select>
            @error('institution_code')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="programme_code">Department / programme</label>
            <select class="input" id="programme_code" name="programme_code" required>@foreach(config('academiq.programmes') as $k => $v)<option value="{{ $k }}" @selected(old('programme_code') == $k)>{{ $v }}</option>@endforeach</select>
            @error('programme_code')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="degree_category">Degree</label>
            <select class="input" id="degree_category" name="degree_category" required>@foreach(config('academiq.degrees') as $k => $v)<option value="{{ $k }}" @selected(old('degree_category') == $k)>{{ $v }}</option>@endforeach</select>
            @error('degree_category')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="passing_year">Passing year</label>
            <select class="input" id="passing_year" name="passing_year" required>@foreach(range(now()->year, config('academiq.min_passing_year')) as $y)<option value="{{ $y }}" @selected(old('passing_year', now()->year) == $y)>{{ $y }}</option>@endforeach</select>
            @error('passing_year')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        <x-field name="issue_date" label="Issue date" type="date" :value="old('issue_date', now()->toDateString())" max="{{ now()->toDateString() }}" />
        <div class="sm:col-span-2"><button class="btn-primary" type="submit">{{ $credential ? 'Issue new version' : 'Issue credential' }}</button></div>
    </form>
</x-layouts.app>
