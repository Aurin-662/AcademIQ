<x-layouts.public title="Verify a report">
<x-page-header title="Verify a report" lead="Confirm that an AcademiQ verification report is genuine." />
<section class="mx-auto max-w-md px-4 py-14 sm:px-6">
    @if(session('result'))
        @if(session('result')['valid'])
            <div role="status" class="mb-6 rounded-lg border border-ok/30 bg-ok/10 p-4 text-sm text-ok">Genuine. Issued for request {{ session('result')['request_id'] }} on {{ session('result')['generated_at'] }} UTC.</div>
        @else
            <div role="alert" class="mb-6 rounded-lg border border-bad/30 bg-bad/10 p-4 text-sm text-bad">No report matches that ID and code.</div>
        @endif
    @endif
    <form method="POST" action="{{ route('report.verify') }}" class="card space-y-4">
        @csrf
        <x-field name="report_id" label="Report ID (RPT-…)" />
        <x-field name="code" label="Authenticity code" />
        <button class="btn-primary w-full" type="submit">Check</button>
    </form>
</section>
</x-layouts.public>
