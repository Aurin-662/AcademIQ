@extends('layouts.app')

@section('content')
<div class="py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-clock-history text-primary me-2"></i>Bulk Verification Audit History</h3>
            <p class="text-secondary small mb-0">Record of previously uploaded batch audits and verification reports.</p>
        </div>
        <div>
            <a href="{{ url('/bulk') }}" class="btn btn-primary">
                <i class="bi bi-upload me-1"></i> New Batch Audit
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Upload Timestamp</th>
                            <th>CSV File Name</th>
                            <th>Total Records</th>
                            <th>Results Summary</th>
                            <th>Audit Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            @php
                                $sum = $req->result_summary ?? [];
                            @endphp
                            <tr>
                                <td>{{ $req->created_at instanceof \DateTimeInterface ? $req->created_at->format('Y-m-d H:i') : $req->created_at }}</td>
                                <td><strong>{{ $req->file_name }}</strong></td>
                                <td>{{ $req->total_records }}</td>
                                <td>
                                    <span class="badge bg-success" title="Genuine">{{ $sum['genuine'] ?? 0 }} Genuine</span>
                                    @if(!empty($sum['tampered']))
                                        <span class="badge bg-danger ms-1" title="Tampered">{{ $sum['tampered'] }} Tampered</span>
                                    @endif
                                    @if(!empty($sum['not_found']))
                                        <span class="badge bg-secondary ms-1" title="Not Found">{{ $sum['not_found'] }} Not Found</span>
                                    @endif
                                    @if(!empty($sum['revoked']))
                                        <span class="badge bg-dark ms-1" title="Revoked">{{ $sum['revoked'] }} Revoked</span>
                                    @endif
                                </td>
                                <td>
                                    @if($req->status === 'completed')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Completed</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Processing</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ url('/bulk/' . $req->_id) }}" class="btn btn-outline-primary btn-sm">
                                        View Report
                                    </a>
                                    <a href="{{ url('/bulk/' . $req->_id . '/download') }}" class="btn btn-outline-success btn-sm ms-1" title="Download CSV">
                                        <i class="bi bi-download"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No bulk verification batches found. <a href="{{ url('/bulk') }}">Upload your first batch</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
                <div class="p-3 border-top">
                    {{ $requests->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
