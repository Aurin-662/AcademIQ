@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-3">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Bulk Credential Verification</h5>
                    <span class="badge bg-primary">Recruiter / Employer Portal</span>
                </div>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">
                    Upload a CSV file containing candidate Document IDs to audit up to 5,000 academic credentials in parallel.
                </p>

                <div class="alert alert-info py-2 d-flex align-items-center small mb-4">
                    <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                    <div>
                        <strong>File Format Requirement:</strong> The first column of your CSV must contain the <code>document_id</code>. Headers like <code>document_id</code> or <code>certificate_id</code> are automatically skipped.
                    </div>
                </div>

                <form method="POST" action="{{ url('/bulk') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-4">
                        <label for="csv_file" class="form-label fw-semibold">Select Candidate CSV File</label>
                        <input class="form-control form-control-lg @error('csv_file') is-invalid @enderror" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                        <div class="form-text">Maximum file size: 10 MB (up to 5,000 rows).</div>
                        @error('csv_file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="p-3 bg-light rounded border mb-4">
                        <h6 class="fw-bold mb-2"><i class="bi bi-file-text me-1"></i> Example CSV Structure</h6>
                        <pre class="bg-white p-2 border rounded small mb-0 font-monospace">document_id
CERT-2024-KUET-1807001
CERT-2024-BUET-1805022
LOR-2024-KUET-AB89EF</pre>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="{{ url('/bulk/history') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-clock-history me-1"></i> Past Uploads
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-upload me-1"></i> Upload & Process Batch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
