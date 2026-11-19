<x-layouts.app :title="\App\Services\Verification\ClaimDescriber::backgroundLabel($task->verification_type)">
    @error('task')<div class="mb-4 rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad" role="alert">{{ $message }}</div>@enderror
    <div class="card mb-6 space-y-2 text-sm">
        <p><span class="text-muted">Applicant</span><br>{{ $task->details['applicant_name'] }} ({{ $task->details['student_reference'] }})</p>
        <p><span class="text-muted">Requested by</span><br>{{ $task->details['requesting_company'] }}</p>
        @if($task->verification_type === 'police')<p><span class="text-muted">Lawful basis stated by the requester</span><br>{{ $task->details['lawful_basis'] }}</p>@endif
        @if($task->verification_type === 'reference')<p><span class="text-muted">Position applied for</span><br>{{ $task->details['position_applied'] }}</p>@endif
        @if($task->verification_type === 'employment')
            <p><span class="text-muted">Employment claimed</span><br>{{ $task->details['job_title'] }} at {{ $task->details['company'] }}, {{ $task->details['period'] }}</p>
        @endif
        <p><span class="text-muted">Status</span><br><x-status :value="$task->status === 'sent' ? 'pending' : $task->status" /> @if($task->due_at) · due {{ $task->due_at->format('Y-m-d') }}@endif</p>
        <p class="text-xs text-muted">The applicant consented to this check. Share only what is needed to answer it.</p>
    </div>

    @if($task->status === 'sent')
    <form method="POST" action="{{ route('external.respond', $task->getKey()) }}" class="card space-y-4">
        @csrf
        <fieldset>
            <legend class="label">Your response</legend>
            <div class="space-y-2 text-sm">
                @foreach($outcomes as $o)
                    <label class="flex items-center gap-2"><input type="radio" name="outcome" value="{{ $o }}" required @checked(old('outcome') === $o)> {{ ucfirst(str_replace('_', ' ', $o)) }}</label>
                @endforeach
            </div>
            @error('outcome')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
        </fieldset>
        @if($task->verification_type === 'police')
            <div><label class="label" for="reference">Your case / certificate reference (optional)</label><input class="input" id="reference" name="reference" maxlength="80" value="{{ old('reference') }}"></div>
        @else
            <div><label class="label" for="remarks">Remarks (optional, kept private; the employer sees only the outcome)</label><textarea class="input" id="remarks" name="remarks" rows="4" maxlength="2000">{{ old('remarks') }}</textarea></div>
        @endif
        <button class="btn-primary" type="submit" onclick="return confirm('Submit this response? It cannot be changed.')">Submit response</button>
    </form>
    @else
        <p class="text-sm text-muted">Response recorded{{ $task->response_timestamp ? ' on '.$task->response_timestamp->format('Y-m-d H:i') : '' }}: <strong>{{ str_replace('_', ' ', $task->outcome ?? $task->status) }}</strong>.</p>
    @endif
</x-layouts.app>
