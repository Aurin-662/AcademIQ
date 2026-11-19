<x-layouts.app title="Verification result">
<div class="max-w-2xl space-y-4">
    @include('employer.verify._outcome', ['log' => $log])
    <div class="flex flex-wrap gap-2 print:hidden">
        <button type="button" class="btn-ghost" onclick="window.print()">Print</button>
        <a class="btn-primary" href="{{ route('employer.verify.pdf', $log->getKey()) }}">Download PDF report</a>
        <a class="btn-ghost" href="{{ route('employer.verify.show', $log->token_id) }}">Change criteria</a>
        <a class="btn-ghost" href="{{ route('employer.verify.index') }}">Check another token</a>
    </div>
</div>
</x-layouts.app>
