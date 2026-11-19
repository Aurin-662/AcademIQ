<x-layouts.app title="Review import">
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <p class="text-sm"><strong>{{ $validCount }}</strong> valid · <strong class="{{ $invalidCount ? 'text-bad' : '' }}">{{ $invalidCount }}</strong> with errors (will be skipped and listed in the error report)</p>
        <form method="POST" action="{{ route('admin.import.commit') }}" class="ml-auto">
            @csrf<input type="hidden" name="import_id" value="{{ $importId }}">
            <button class="btn-primary" type="submit" @disabled($validCount === 0)>Issue {{ $validCount }} credential(s)</button>
        </form>
        <a class="btn-ghost" href="{{ route('admin.import') }}">Cancel</a>
    </div>
    @error('file')<div class="mb-4 rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad">{{ $message }}</div>@enderror
    <div class="card overflow-x-auto !p-0">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-soft text-muted"><tr><th class="px-4 py-2">Row</th><th class="px-4 py-2">Student</th><th class="px-4 py-2">Name</th><th class="px-4 py-2">Institution</th><th class="px-4 py-2">Degree</th><th class="px-4 py-2">Dept</th><th class="px-4 py-2">CGPA</th><th class="px-4 py-2">Year</th><th class="px-4 py-2">Result</th></tr></thead>
            <tbody class="divide-y divide-slate-soft">
            @foreach($preview as $no => $r)
                <tr class="{{ $r['errors'] ? 'bg-bad/5' : '' }}">
                    <td class="px-4 py-2 text-muted">{{ $no }}</td><td class="px-4 py-2 font-mono">{{ $r['student_id'] }}</td><td class="px-4 py-2">{{ $r['name'] }}</td>
                    <td class="px-4 py-2">{{ $r['institution'] }}</td><td class="px-4 py-2">{{ $r['degree'] }}</td><td class="px-4 py-2">{{ $r['department'] }}</td>
                    <td class="px-4 py-2">{{ $r['cgpa'] }}</td><td class="px-4 py-2">{{ $r['passing_year'] }}</td>
                    <td class="px-4 py-2">
                        @if($r['errors'])<span class="text-bad">{{ implode('; ', $r['errors']) }}</span>
                        @else<span class="text-ok">OK</span>@endif
                        @foreach($r['warnings'] as $w)<br><span class="text-xs text-muted">{{ $w }}</span>@endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
