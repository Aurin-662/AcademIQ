<x-layouts.app title="New verification request">
<form method="POST" action="{{ route('employer.requests.store') }}" class="space-y-6" x-data="{ kind: '{{ old('academic_kind', '') }}', police: {{ old('police') ? 'true' : 'false' }}, reference: {{ old('reference') ? 'true' : 'false' }}, employment: {{ old('employment') ? 'true' : 'false' }} }">
    @csrf
    @error('academic_kind')<div class="rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad">{{ $message }}</div>@enderror
    <div class="card space-y-4">
        <h2 class="text-lg font-bold">Applicant</h2>
        <x-field name="student_reference" label="Student reference (provided by the applicant)" />
        <p class="text-xs text-muted">The applicant must consent before any check starts.</p>
    </div>

    <div class="card space-y-4">
        <h2 class="text-lg font-bold">Academic claim (zero-knowledge)</h2>
        <div><label class="label" for="academic_kind">Claim to verify</label>
            <select class="input" id="academic_kind" name="academic_kind" x-model="kind">
                <option value="">None</option><option value="cgpa">CGPA at least…</option><option value="programme">Programme is…</option><option value="degree">Degree category is…</option><option value="issuer">Credential valid and issued by KUET</option>
            </select></div>
        <div x-show="kind === 'cgpa'" x-cloak><label class="label" for="cgpa_min">Minimum CGPA</label><input class="input" id="cgpa_min" name="cgpa_min" value="{{ old('cgpa_min') }}" placeholder="3.00" inputmode="decimal">@error('cgpa_min')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        <div x-show="kind === 'programme'" x-cloak><label class="label" for="programme_code">Programme</label><select class="input" id="programme_code" name="programme_code">@foreach(config('academiq.programmes') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div x-show="kind === 'degree'" x-cloak><label class="label" for="degree_category">Degree</label><select class="input" id="degree_category" name="degree_category">@foreach(config('academiq.degrees') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    </div>

    <div class="card space-y-4">
        <h2 class="text-lg font-bold">Background checks</h2>
        <p class="text-xs text-muted">Responses are submitted manually by authorized participants through restricted portals.</p>

        <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="police" value="1" x-model="police"> Police verification</label>
        <div x-show="police" x-cloak class="space-y-3 pl-6">
            <div><label class="label" for="police_participant">Verification participant</label><select class="input" id="police_participant" name="police_participant">@foreach($police as $p)<option value="{{ $p->getKey() }}">{{ $p->name }}</option>@endforeach</select>@error('police_participant')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="police_basis">Lawful basis / authorization for this check</label><input class="input" id="police_basis" name="police_basis" value="{{ old('police_basis') }}" maxlength="300">@error('police_basis')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        </div>

        <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="reference" value="1" x-model="reference"> Reference check</label>
        <div x-show="reference" x-cloak class="space-y-3 pl-6">
            <div><label class="label" for="reference_participant">Referee</label><select class="input" id="reference_participant" name="reference_participant">@foreach($referees as $p)<option value="{{ $p->getKey() }}">{{ $p->name }}</option>@endforeach</select>@error('reference_participant')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="reference_position">Position applied for</label><input class="input" id="reference_position" name="reference_position" value="{{ old('reference_position') }}">@error('reference_position')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        </div>

        <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="employment" value="1" x-model="employment"> Work experience verification</label>
        <div x-show="employment" x-cloak class="space-y-3 pl-6">
            <div><label class="label" for="employment_participant">Previous employer</label><select class="input" id="employment_participant" name="employment_participant">@foreach($employers as $p)<option value="{{ $p->getKey() }}">{{ $p->name }}</option>@endforeach</select>@error('employment_participant')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="employment_company">Company claimed</label><input class="input" id="employment_company" name="employment_company" value="{{ old('employment_company') }}">@error('employment_company')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="employment_title">Job title claimed</label><input class="input" id="employment_title" name="employment_title" value="{{ old('employment_title') }}">@error('employment_title')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="employment_period">Period claimed</label><input class="input" id="employment_period" name="employment_period" value="{{ old('employment_period') }}" placeholder="2022-01 to 2024-06">@error('employment_period')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        </div>
    </div>
    <button class="btn-primary" type="submit">Submit request</button>
</form>
</x-layouts.app>
