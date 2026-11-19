@props(['value'])
@php($cls = match ($value) { 'active', 'verified', 'confirmed' => 'bg-ok/10 text-ok', 'revoked', 'rejected', 'suspended' => 'bg-bad/10 text-bad', 'superseded', 'retired', 'pending', 'expired' => 'bg-warn/15 text-warn', default => 'bg-slate-soft text-muted' })
<span class="badge {{ $cls }}">{{ ucfirst($value) }}</span>
