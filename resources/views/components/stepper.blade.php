@props(['steps', 'current' => 1])
{{-- $current is 1-based: steps before it are done, that one is active. --}}
<ol class="mb-8 flex flex-wrap items-center gap-2 text-sm" aria-label="Progress">
    @foreach($steps as $i => $label)
        @php($n = $i + 1)
        @php($state = $n < $current ? 'done' : ($n === $current ? 'active' : 'todo'))
        <li class="flex items-center gap-2 {{ $state === 'todo' ? 'text-muted' : 'text-navy' }}" @if($state === 'active') aria-current="step" @endif>
            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold
                {{ $state === 'done' ? 'bg-ok text-white' : ($state === 'active' ? 'bg-kuet text-white' : 'bg-slate-soft text-muted') }}">
                {{ $state === 'done' ? '✓' : $n }}
            </span>
            <span class="{{ $state === 'active' ? 'font-semibold' : '' }}">{{ $label }}</span>
        </li>
        @if(! $loop->last)
            <li class="text-slate-300" aria-hidden="true">/</li>
        @endif
    @endforeach
</ol>