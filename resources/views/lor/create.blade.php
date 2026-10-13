@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-3">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-pen-fill me-2"></i>Issue Recommendation Letter (LOR)</h5>
                    <span class="badge bg-primary">Faculty Portal</span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-0 fw-bold">{{ $teacher->name }}</h6>
                            <small class="text-muted">{{ $teacher->designation }} &bull; {{ $teacher->department }} &bull; {{ $teacher->institution_code }}</small>
                        </div>
                        <div>
                            @if($teacher->verified)
                                <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified Faculty</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="bi bi-info-circle me-1"></i>Pending Faculty Verification</span>
                            @endif
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ url('/lor') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="recipient_name" class="form-label fw-semibold">Student / Candidate Full Name</label>
                        <input type="text" id="recipient_name" name="recipient_name" class="form-control form-control-lg @error('recipient_name') is-invalid @enderror" value="{{ old('recipient_name') }}" placeholder="e.g. Tanjimul Islam" required autofocus>
                        @error('recipient_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="purpose" class="form-label fw-semibold">Purpose of Recommendation</label>
                        <input type="text" id="purpose" name="purpose" class="form-control @error('purpose') is-invalid @enderror" value="{{ old('purpose') }}" placeholder="e.g. Graduate Studies Application / Software Engineering Role at Google" required>
                        <div class="form-text">
                            This purpose is cryptographically tied into the document's HMAC signature to prevent reuse for unauthorized intents.
                        </div>
                        @error('purpose')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ url('/') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-shield-lock-fill me-1"></i> Sign & Issue LOR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
