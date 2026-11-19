<x-layouts.app title="Bulk issue credentials">
    @error('file')<div class="mb-4 rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad">{{ $message }}</div>@enderror
    <form method="POST" action="{{ route('admin.import.preview') }}" enctype="multipart/form-data" class="card mb-8 grid gap-4">
        @csrf
        <h2 class="text-lg font-bold">Import from CSV or XLSX</h2>
        <p class="text-sm text-muted">Columns: {{ implode(', ', \App\Services\Credentials\BulkImport::COLUMNS) }}. Up to {{ config('academiq.import_max_rows') }} rows. You review every row before anything is issued.
            <a class="underline" href="{{ route('admin.import.template') }}">Download template</a></p>
        <div><label class="label" for="file">File</label><input class="input" id="file" name="file" type="file" accept=".csv,.xlsx" required></div>
        <div><button class="btn-primary" type="submit">Upload and preview</button></div>
    </form>

    <form method="POST" action="{{ route('admin.import.revoke') }}" class="card grid gap-4" onsubmit="return confirm('Revoke all active credentials of the listed students?')">
        @csrf
        <h2 class="text-lg font-bold">Bulk revoke</h2>
        <p class="text-sm text-muted">Revokes every active credential of each listed student. Separate IDs with new lines, spaces or commas. Re-issue afterwards from the import or single form.</p>
        <div><label class="label" for="student_ids">Student IDs</label><textarea class="input" id="student_ids" name="student_ids" rows="4" required>{{ old('student_ids') }}</textarea>
            @error('student_ids')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        <x-field name="reason" label="Reason (min 5 characters)" />
        <div><button class="btn-ghost" type="submit">Revoke credentials</button></div>
    </form>
</x-layouts.app>
