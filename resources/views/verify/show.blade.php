@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-3">
    <div class="col-md-9 col-lg-8">
        {{-- Header Status Card --}}
        @if($result['result'] === 'genuine')
            <div class="card border-0 shadow-sm mb-4 border-top border-5 border-success">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-sm-row align-items-center gap-3 text-center text-sm-start">
                        <div class="p-3 bg-success-subtle text-success rounded-circle">
                            <i class="bi bi-shield-fill-check fs-1"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="badge bg-success px-3 py-1 fs-6 mb-1">GENUINE CREDENTIAL</span>
                            <h3 class="fw-bold mb-1">Authenticity Verified</h3>
                            <p class="text-secondary mb-0 small">
                                The cryptographic signature and database records match precisely. This document was officially issued.
                            </p>
                        </div>
                    </div>

                    @if(!empty($result['is_under_review']))
                        <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                            <div>
                                <strong>Under Administrative Review:</strong> Although cryptographic signatures match, this document has been flagged for audit review.
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        @elseif($result['result'] === 'tampered')
            <div class="card border-0 shadow-sm mb-4 border-top border-5 border-danger">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-sm-row align-items-center gap-3 text-center text-sm-start">
                        <div class="p-3 bg-danger-subtle text-danger rounded-circle">
                            <i class="bi bi-shield-slash-fill fs-1"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="badge bg-danger px-3 py-1 fs-6 mb-1">TAMPERED / FORGERY DETECTED</span>
                            <h3 class="fw-bold text-danger mb-1">Cryptographic Hash Mismatch</h3>
                            <p class="text-secondary mb-0 small">
                                <strong>Warning:</strong> The data in this record does not match the HMAC-SHA256 signature calculated at issuance. Document contents or metadata may have been modified without authorization.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        @elseif($result['result'] === 'revoked')
            <div class="card border-0 shadow-sm mb-4 border-top border-5 border-dark">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-sm-row align-items-center gap-3 text-center text-sm-start">
                        <div class="p-3 bg-dark-subtle text-dark rounded-circle">
                            <i class="bi bi-x-octagon-fill fs-1"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="badge bg-dark px-3 py-1 fs-6 mb-1">REVOKED DOCUMENT</span>
                            <h3 class="fw-bold text-dark mb-1">Credential Officially Revoked</h3>
                            <p class="text-secondary mb-0 small">
                                This document was originally valid but has been officially revoked and cancelled by the issuing authority.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        @else
            <div class="card border-0 shadow-sm mb-4 border-top border-5 border-secondary">
                <div class="card-body p-4 text-center">
                    <div class="p-3 bg-secondary-subtle text-secondary rounded-circle d-inline-block mb-3">
                        <i class="bi bi-question-circle-fill fs-1"></i>
                    </div>
                    <span class="badge bg-secondary px-3 py-1 fs-6 mb-2">NOT FOUND</span>
                    <h3 class="fw-bold mb-1">Document Record Not Found</h3>
                    <p class="text-secondary small mb-4">
                        No record exists for Document ID: <code>{{ $result['document_id'] }}</code>. Please check for typos or contact the issuing institution.
                    </p>
                    <a href="{{ url('/verify') }}" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i> Search Another ID
                    </a>
                </div>
            </div>
        @endif

        {{-- Document Record Details Table (if record exists) --}}
        @if(!empty($result['document']))
            @php
                $doc = $result['document'];
                $recipientName = $doc['recipient_name'];
                if (config('academiq.mask_recipient')) {
                    $parts = explode(' ', trim($recipientName));
                    $masked = array_map(fn($p) => strtoupper(substr($p, 0, 1)) . '.', $parts);
                    $recipientName = implode(' ', $masked);
                }
            @endphp
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-file-earmark-text me-2"></i>Document Details
                    </h5>
                    <span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $doc['document_type']) }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th class="w-35 text-secondary">Document ID</th>
                                    <td><strong class="text-primary">{{ $doc['document_id'] }}</strong></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">Recipient / Student</th>
                                    <td><strong class="fs-6">{{ $recipientName }}</strong></td>
                                </tr>
                                @if(!empty($doc['roll_no']))
                                <tr>
                                    <th class="text-secondary">Roll / Student ID</th>
                                    <td>{{ $doc['roll_no'] }}</td>
                                </tr>
                                @endif
                                @if(!empty($doc['department']))
                                <tr>
                                    <th class="text-secondary">Department</th>
                                    <td>{{ $doc['department'] }}</td>
                                </tr>
                                @endif
                                @if(!empty($doc['passing_year']))
                                <tr>
                                    <th class="text-secondary">Passing Year</th>
                                    <td>{{ $doc['passing_year'] }}</td>
                                </tr>
                                @endif
                                @if(!empty($doc['result_grade']))
                                <tr>
                                    <th class="text-secondary">Grade / Result</th>
                                    <td>{{ $doc['result_grade'] }}</td>
                                </tr>
                                @endif
                                @if(!empty($doc['purpose']))
                                <tr>
                                    <th class="text-secondary">Purpose</th>
                                    <td>{{ $doc['purpose'] }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <th class="text-secondary">Issuing Authority</th>
                                    <td>
                                        <div><strong>{{ $doc['issuer_name'] }}</strong> ({{ $doc['issuer_id'] }})</div>
                                        @if($doc['issuer_type'] === 'teacher' && !empty($result['teacher']))
                                            <div class="mt-1">
                                                <span class="text-muted small">{{ $result['teacher']['designation'] }} ({{ $result['teacher']['department'] }})</span>
                                                @if(!empty($result['teacher']['verified']))
                                                    <span class="badge bg-success ms-1">
                                                        <i class="bi bi-patch-check-fill me-1"></i>Verified Faculty
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning text-dark ms-1">
                                                        <i class="bi bi-exclamation-circle me-1"></i>Issuer Not Verified
                                                    </span>
                                                @endif
                                            </div>
                                        @elseif($doc['issuer_type'] === 'teacher' && empty($result['teacher']))
                                            <span class="badge bg-warning text-dark mt-1">Issuer Not Verified</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">Issue Date</th>
                                    <td>{{ $doc['issue_date'] }}</td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">HMAC-SHA256 Hash</th>
                                    <td><code class="text-break small">{{ $doc['hash'] }}</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-4 pt-3 border-top">
                        <div class="small text-muted">
                            Verified at: {{ $result['verified_at'] }}
                            @if(!empty($result['cached']))
                                <span class="badge bg-info-subtle text-info border ms-1">Served from Cache</span>
                            @endif
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ url('/documents/' . $doc['document_id'] . '/print') }}" class="btn btn-outline-dark btn-sm" target="_blank">
                                <i class="bi bi-printer me-1"></i> Printable Certificate
                            </a>
                            <a href="{{ url('/verify') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-search me-1"></i> Verify Another
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
