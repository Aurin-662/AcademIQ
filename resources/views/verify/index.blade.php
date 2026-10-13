@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-md-7 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white py-3 text-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-shield-check text-primary me-2"></i>Verify Academic Credential</h5>
                <small class="text-secondary">Instant cryptographic authenticity & integrity verification</small>
            </div>
            <div class="card-body p-4">
                <p class="text-muted text-center mb-4">
                    Enter the unique Document ID printed on the certificate or recommendation letter to check its authenticity.
                </p>

                <form method="GET" action="{{ url('/verify') }}">
                    <div class="mb-3">
                        <label for="doc_id" class="form-label fw-semibold">Document / Certificate ID</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light"><i class="bi bi-qr-code"></i></span>
                            <input type="text" class="form-control" id="doc_id" name="id" placeholder="e.g. CERT-2026-KUET-AB12CD" required autofocus>
                        </div>
                        <div class="form-text">
                            Case-sensitive ID as printed on the physical credential or PDF.
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-search me-1"></i> Check Credential Authenticity
                        </button>
                    </div>
                </form>

                <div class="border-top pt-3 text-center small text-muted">
                    <span>Need to verify large batches? <a href="{{ url('/bulk') }}" class="fw-semibold">Use Bulk Verification (CSV)</a></span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
