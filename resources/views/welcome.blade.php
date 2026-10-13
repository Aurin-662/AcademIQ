@extends('layouts.app')

@section('content')
<div class="row align-items-center py-5">
    <div class="col-lg-7 text-center text-lg-start mb-4 mb-lg-0">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-3">
            <i class="bi bi-shield-fill-check me-1"></i> Academic Credential Verification & Fraud Analytics
        </span>
        <h1 class="display-5 fw-bold text-dark lh-tight mb-3">
            Instant, Tamper-Evident Verification for Academic Credentials
        </h1>
        <p class="lead text-secondary mb-4">
            AcademIQ empowers universities, faculty, and employers with high-throughput cryptographic verification, keyed HMAC-SHA256 protection, and machine learning fraud analytics.
        </p>

        <!-- Quick Verify Form -->
        <div class="card p-3 shadow-sm border mb-4 bg-white">
            <form action="{{ url('/verify') }}" method="GET" class="d-flex flex-column flex-sm-row gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-qr-code-scan"></i></span>
                    <input type="text" name="id" class="form-control form-control-lg" placeholder="Enter Document ID (e.g. CERT-2026-KUET-...)" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg px-4 text-nowrap">
                    <i class="bi bi-search me-1"></i> Verify
                </button>
            </form>
            <div class="small text-muted mt-2 d-flex align-items-center gap-2">
                <i class="bi bi-info-circle"></i> Have a printed document? Simply scan the on-page QR code with your mobile camera.
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-start">
            <a href="{{ url('/verify') }}" class="btn btn-outline-dark">
                <i class="bi bi-search me-1"></i> Verify Portal
            </a>
            <a href="{{ url('/bulk') }}" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Bulk Verification
            </a>
            <a href="{{ url('/teachers/search') }}" class="btn btn-outline-secondary">
                <i class="bi bi-person-badge me-1"></i> Search Teachers
            </a>
            @guest
                <a href="{{ url('/login') }}" class="btn btn-success">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Portal Login
                </a>
            @endguest
        </div>
    </div>

    <div class="col-lg-5">
        <div class="row g-3">
            <div class="col-12">
                <div class="card p-4 border-0 shadow-sm bg-white">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="p-3 bg-primary text-white rounded-3">
                            <i class="bi bi-shield-lock fs-3"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">HMAC-SHA256 Tamper Evident</h5>
                            <small class="text-muted">Keyed secret fingerprinting</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Any direct modification to student details, marks, or passing years in the database automatically triggers a tamper detection flag upon verification.
                    </p>
                </div>
            </div>

            <div class="col-12">
                <div class="card p-4 border-0 shadow-sm bg-white">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="p-3 bg-success text-white rounded-3">
                            <i class="bi bi-lightning-charge fs-3"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">High-Throughput In-Memory Caching</h5>
                            <small class="text-muted">Sub-millisecond latency</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Frequently checked credentials are held in low-latency cache with immediate cache invalidation upon any administrative status change.
                    </p>
                </div>
            </div>

            <div class="col-12">
                <div class="card p-4 border-0 shadow-sm bg-white">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="p-3 bg-danger text-white rounded-3">
                            <i class="bi bi-cpu fs-3"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Applied Fraud Analytics</h5>
                            <small class="text-muted">IsolationForest & RapidFuzz</small>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Detects duplicate student rolls, near-duplicate spelling anomalies, and burst verification attack patterns in real time.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5 g-4 border-top pt-5">
    <div class="col-md-4">
        <div class="card h-100 p-4 border-0 bg-white">
            <h5 class="fw-bold"><i class="bi bi-bank text-primary me-2"></i>Institutions</h5>
            <p class="text-secondary small">
                Accredited academic institutions issue verifiable graduation certificates and marksheets with cryptographic signatures.
            </p>
            <div class="mt-auto">
                <a href="{{ route('documents.create') }}" class="btn btn-sm btn-outline-primary">Issue Certificate &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 p-4 border-0 bg-white">
            <h5 class="fw-bold"><i class="bi bi-mortarboard text-success me-2"></i>Faculty & Teachers</h5>
            <p class="text-secondary small">
                Verified faculty members issue tamper-proof Letters of Recommendation (LOR) for students seeking employment or higher studies.
            </p>
            <div class="mt-auto">
                <a href="{{ url('/teachers/search') }}" class="btn btn-sm btn-outline-success">Browse Directory &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 p-4 border-0 bg-white">
            <h5 class="fw-bold"><i class="bi bi-briefcase text-dark me-2"></i>Employers & Recruiters</h5>
            <p class="text-secondary small">
                Verify thousands of graduate candidates in seconds using single-click bulk CSV verification with downloadable audit logs.
            </p>
            <div class="mt-auto">
                <a href="{{ url('/bulk') }}" class="btn btn-sm btn-outline-dark">Bulk Upload &rarr;</a>
            </div>
        </div>
    </div>
</div>
@endsection
