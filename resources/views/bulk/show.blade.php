@extends('layouts.app')

@section('content')
<div class="py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-check text-primary me-2"></i>Bulk Verification Report</h3>
            <p class="text-secondary small mb-0">File: <code>{{ $bulkReq->file_name }}</code> &bull; Audited on: {{ $bulkReq->created_at instanceof \DateTimeInterface ? $bulkReq->created_at->format('M d, Y H:i T') : $bulkReq->created_at }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url('/bulk/' . $bulkReq->_id . '/download') }}" class="btn btn-success">
                <i class="bi bi-download me-1"></i> Download as CSV
            </a>
            <a href="{{ url('/bulk') }}" class="btn btn-outline-primary">
                <i class="bi bi-plus-lg me-1"></i> Verify New Batch
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    @php
        $sum = $bulkReq->result_summary ?? [];
        $total = $bulkReq->total_records ?? 0;
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 border-0 shadow-sm bg-white border-start border-4 border-primary">
                <div class="text-muted small fw-semibold">TOTAL AUDITED</div>
                <div class="fs-2 fw-bold text-dark">{{ $total }}</div>
                <div class="text-secondary small">Candidate documents</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-0 shadow-sm bg-white border-start border-4 border-success">
                <div class="text-success small fw-semibold">GENUINE</div>
                <div class="fs-2 fw-bold text-success">{{ $sum['genuine'] ?? 0 }}</div>
                <div class="text-secondary small">Cryptographically authentic</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-0 shadow-sm bg-white border-start border-4 border-danger">
                <div class="text-danger small fw-semibold">TAMPERED / MISMATCH</div>
                <div class="fs-2 fw-bold text-danger">{{ $sum['tampered'] ?? 0 }}</div>
                <div class="text-secondary small">Hash integrity failed</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 border-0 shadow-sm bg-white border-start border-4 border-warning">
                <div class="text-warning-emphasis small fw-semibold">NOT FOUND / REVOKED</div>
                <div class="fs-2 fw-bold text-dark">{{ ($sum['not_found'] ?? 0) + ($sum['revoked'] ?? 0) }}</div>
                <div class="text-secondary small">{{ $sum['not_found'] ?? 0 }} not found, {{ $sum['revoked'] ?? 0 }} revoked</div>
            </div>
        </div>
    </div>

    {{-- Results Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Detailed Row Results</h5>
            <span class="badge bg-secondary">{{ count($bulkReq->results ?? []) }} records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Document ID</th>
                            <th>Recipient Name</th>
                            <th>Verification Status</th>
                            <th class="text-end">Live Verify</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bulkReq->results ?? [] as $index => $row)
                            <tr>
                                <td class="text-muted small">{{ $index + 1 }}</td>
                                <td>
                                    <a href="{{ url('/verify/' . $row['document_id']) }}" class="fw-semibold text-decoration-none">
                                        {{ $row['document_id'] }}
                                    </a>
                                </td>
                                <td>{{ $row['recipient_name'] }}</td>
                                <td>
                                    @if($row['result'] === 'genuine')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle-fill me-1"></i> Genuine
                                        </span>
                                    @elseif($row['result'] === 'tampered')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-shield-slash-fill me-1"></i> Tampered / Mismatch
                                        </span>
                                    @elseif($row['result'] === 'revoked')
                                        <span class="badge bg-dark-subtle text-dark border border-dark-subtle">
                                            <i class="bi bi-x-circle-fill me-1"></i> Revoked
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                            <i class="bi bi-question-circle me-1"></i> Not Found
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ url('/verify/' . $row['document_id']) }}" class="btn btn-outline-primary btn-sm" target="_blank">
                                        View Details &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No results recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
