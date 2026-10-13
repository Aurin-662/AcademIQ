@extends('layouts.app')

@section('content')
<div class="py-3">
    {{-- Teacher Profile Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-primary text-white rounded-circle">
                        <i class="bi bi-mortarboard fs-2"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h3 class="fw-bold mb-0">{{ $teacher->name }}</h3>
                            @if($teacher->verified)
                                <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified Faculty</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Issuer Not Verified</span>
                            @endif
                        </div>
                        <div class="text-secondary mt-1">
                            <strong>{{ $teacher->designation }}</strong> &bull; Department of {{ $teacher->department }}
                        </div>
                        <div class="text-muted small mt-1">
                            <i class="bi bi-bank me-1"></i>{{ $institution->name ?? $teacher->institution_code }} ({{ $teacher->institution_code }})
                            &bull; <i class="bi bi-card-heading ms-2 me-1"></i>ID: <code>{{ $teacher->teacher_id }}</code>
                        </div>
                    </div>
                </div>

                @if(auth()->check() && auth()->user()->role === 'teacher' && auth()->user()->id === $teacher->user_id)
                    <div>
                        <a href="{{ url('/lor/create') }}" class="btn btn-primary">
                            <i class="bi bi-pen me-1"></i> Issue New LOR
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Issued Recommendation Letters Section --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light py-3">
            <div class="row align-items-center g-2">
                <div class="col-md-6">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-envelope-paper me-2"></i>Public Issued Letters Registry
                    </h5>
                    <small class="text-muted">Letter metadata is publicly verifiable; confidential contents are never disclosed.</small>
                </div>
                <div class="col-md-6">
                    {{-- Search box: "Did this teacher issue a letter to ___?" --}}
                    <form action="{{ url('/teachers/' . $teacher->teacher_id) }}" method="GET">
                        <div class="input-group">
                            <input type="text" name="recipient" class="form-control form-control-sm" placeholder="Did this teacher issue a letter to..." value="{{ $searchRecipient }}">
                            <button class="btn btn-outline-primary btn-sm" type="submit">
                                <i class="bi bi-search"></i> Check
                            </button>
                            @if($searchRecipient !== '')
                                <a href="{{ url('/teachers/' . $teacher->teacher_id) }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if($letters->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-journal-x fs-2 mb-2"></i>
                    <div>No recommendation letters found matching your criteria.</div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Document ID</th>
                                <th>Recipient</th>
                                <th>Issue Date</th>
                                <th>Status</th>
                                <th class="text-end">Verification</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($letters as $letter)
                                @php
                                    $recipientDisplay = $letter->recipient_name;
                                    if (config('academiq.mask_recipient')) {
                                        $parts = explode(' ', trim($recipientDisplay));
                                        $masked = array_map(fn($p) => strtoupper(substr($p, 0, 1)) . '.', $parts);
                                        $recipientDisplay = implode(' ', $masked);
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ url('/verify/' . $letter->document_id) }}" class="fw-semibold text-decoration-none">
                                            {{ $letter->document_id }}
                                        </a>
                                    </td>
                                    <td><strong>{{ $recipientDisplay }}</strong></td>
                                    <td>{{ $letter->issue_date instanceof \DateTimeInterface ? $letter->issue_date->format('M d, Y') : $letter->issue_date }}</td>
                                    <td>
                                        @if($letter->status === 'active')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                        @elseif($letter->status === 'flagged')
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Under Review</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ ucfirst($letter->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ url('/verify/' . $letter->document_id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-shield-check me-1"></i> Verify
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($letters->hasPages())
                    <div class="p-3 border-top">
                        {{ $letters->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
