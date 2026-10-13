@extends('layouts.app')

@section('content')
<div class="py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-shield-exclamation text-danger me-2"></i>Fraud & Anomaly Review Center</h3>
            <p class="text-secondary small mb-0">Review potential credential forgeries, duplicate roll anomalies, and automated verification attacks.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ url('/admin/fraud?status=pending_review') }}" class="btn btn-outline-warning btn-sm {{ $status === 'pending_review' ? 'active' : '' }}">
                <i class="bi bi-hourglass-split me-1"></i> Pending Review
            </a>
            <a href="{{ url('/admin/fraud?status=confirmed') }}" class="btn btn-outline-danger btn-sm {{ $status === 'confirmed' ? 'active' : '' }}">
                <i class="bi bi-check-circle me-1"></i> Confirmed
            </a>
            <a href="{{ url('/admin/fraud?status=dismissed') }}" class="btn btn-outline-secondary btn-sm {{ $status === 'dismissed' ? 'active' : '' }}">
                <i class="bi bi-x-circle me-1"></i> Dismissed
            </a>
            <a href="{{ url('/admin/fraud') }}" class="btn btn-outline-dark btn-sm {{ !$status ? 'active' : '' }}">
                All Flags
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Flag Type</th>
                            <th>Target Document ID</th>
                            <th>Risk Score</th>
                            <th>Detection Evidence</th>
                            <th>Detected At</th>
                            <th>Status</th>
                            <th class="text-end">Review Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($flags as $flag)
                            <tr>
                                <td>
                                    @if($flag->flag_type === 'duplicate_roll')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-people-fill me-1"></i> Duplicate Roll
                                        </span>
                                    @elseif($flag->flag_type === 'near_duplicate_name')
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-spellcheck me-1"></i> Near-Duplicate Name
                                        </span>
                                    @elseif($flag->flag_type === 'hash_mismatch')
                                        <span class="badge bg-danger px-2 py-1">
                                            <i class="bi bi-shield-slash-fill me-1"></i> Hash Mismatch
                                        </span>
                                    @elseif($flag->flag_type === 'anomalous_verification_pattern')
                                        <span class="badge bg-dark px-2 py-1">
                                            <i class="bi bi-cpu-fill me-1"></i> Traffic Anomaly (iForest)
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">{{ $flag->flag_type }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($flag->certificate_id)
                                        <a href="{{ url('/verify/' . $flag->certificate_id) }}" class="fw-semibold text-decoration-none" target="_blank">
                                            {{ $flag->certificate_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                            <div class="progress-bar {{ $flag->score >= 0.7 ? 'bg-danger' : ($flag->score >= 0.4 ? 'bg-warning' : 'bg-info') }}" style="width: {{ min(100, $flag->score * 100) }}%"></div>
                                        </div>
                                        <span class="small fw-semibold">{{ round($flag->score, 2) }}</span>
                                    </div>
                                </td>
                                <td style="max-width: 320px;">
                                    <div class="small">
                                        @if(is_array($flag->details))
                                            @foreach($flag->details as $k => $v)
                                                @if(is_scalar($v))
                                                    <span class="text-muted">{{ $k }}:</span> <strong>{{ $v }}</strong><br>
                                                @endif
                                            @endforeach
                                        @else
                                            {{ json_encode($flag->details) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    {{ $flag->detected_at instanceof \DateTimeInterface ? $flag->detected_at->format('M d, Y H:i') : $flag->detected_at }}
                                </td>
                                <td>
                                    @if($flag->status === 'pending_review')
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
                                    @elseif($flag->status === 'confirmed')
                                        <span class="badge bg-danger"><i class="bi bi-check-circle-fill me-1"></i>Confirmed</span>
                                    @elseif($flag->status === 'dismissed')
                                        <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Dismissed</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($flag->status === 'pending_review')
                                        <div class="btn-group btn-group-sm">
                                            <form action="{{ url('/admin/fraud/' . ($flag->_id ?? $flag->id) . '/confirm') }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Confirm Fraud & Flag Document">
                                                    <i class="bi bi-check-lg"></i> Confirm
                                                </button>
                                            </form>
                                            <form action="{{ url('/admin/fraud/' . ($flag->_id ?? $flag->id) . '/dismiss') }}" method="POST" class="d-inline ms-1">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Dismiss Flag">
                                                    <i class="bi bi-x-lg"></i> Dismiss
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <small class="text-muted">Reviewed by {{ $flag->reviewed_by ?? 'Admin' }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No fraud flags recorded in this view.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($flags->hasPages())
                <div class="p-3 border-top">
                    {{ $flags->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
