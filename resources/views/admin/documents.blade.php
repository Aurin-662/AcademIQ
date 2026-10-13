@extends('layouts.app')

@section('content')
<div class="py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-files text-primary me-2"></i>Document Registry & Revocation</h3>
            <p class="text-secondary small mb-0">System-wide registry of all issued academic certificates and letters of recommendation.</p>
        </div>
        <div>
            <form action="{{ url('/admin/documents') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by ID, recipient, roll..." value="{{ $q }}">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="flagged" {{ $status === 'flagged' ? 'selected' : '' }}>Flagged</option>
                    <option value="revoked" {{ $status === 'revoked' ? 'selected' : '' }}>Revoked</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Document ID</th>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Issuer</th>
                            <th>Issue Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr>
                                <td>
                                    <a href="{{ url('/verify/' . $doc->document_id) }}" class="fw-semibold text-decoration-none">
                                        {{ $doc->document_id }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $doc->document_type) }}</span>
                                </td>
                                <td>
                                    <strong>{{ $doc->recipient_name }}</strong>
                                    @if($doc->roll_no)
                                        <div class="text-muted small">Roll: {{ $doc->roll_no }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $doc->issuer_name }}</div>
                                    <div class="text-muted small">Code: {{ $doc->issuer_id }}</div>
                                </td>
                                <td>
                                    {{ $doc->issue_date instanceof \DateTimeInterface ? $doc->issue_date->format('Y-m-d') : $doc->issue_date }}
                                </td>
                                <td>
                                    @if($doc->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @elseif($doc->status === 'flagged')
                                        <span class="badge bg-warning text-dark">Flagged</span>
                                    @elseif($doc->status === 'revoked')
                                        <span class="badge bg-danger" title="{{ $doc->revocation_reason ?? 'Revoked' }}">Revoked</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ url('/documents/' . $doc->document_id . '/print') }}" class="btn btn-outline-dark btn-sm" target="_blank" title="Printable">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <a href="{{ url('/verify/' . $doc->document_id) }}" class="btn btn-outline-primary btn-sm" target="_blank" title="Verify">
                                            <i class="bi bi-shield-check"></i>
                                        </a>
                                        @if($doc->status !== 'revoked')
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#revokeModal{{ $doc->_id ?? md5($doc->document_id) }}" title="Revoke Credential">
                                                <i class="bi bi-x-circle"></i> Revoke
                                            </button>

                                            <!-- Revoke Modal -->
                                            <div class="modal fade text-start" id="revokeModal{{ $doc->_id ?? md5($doc->document_id) }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="{{ url('/admin/documents/' . $doc->document_id . '/revoke') }}" method="POST">
                                                            @csrf
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title">Revoke Credential: {{ $doc->document_id }}</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="text-danger fw-semibold">
                                                                    Are you sure you want to officially revoke this document? This invalidates its verification status permanently.
                                                                </p>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Official Revocation Reason</label>
                                                                    <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Forged marksheet submission or administrative disciplinary cancellation." required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Confirm Revocation</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No documents found matching the search filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($documents->hasPages())
                <div class="p-3 border-top">
                    {{ $documents->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
