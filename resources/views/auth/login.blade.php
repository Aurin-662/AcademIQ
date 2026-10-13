@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white py-3 text-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In to AcademIQ</h5>
                <small class="text-secondary">Authorized institution, teacher, or employer portal</small>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label text-muted small" for="remember">Remember me on this device</label>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Sign In
                        </button>
                    </div>

                    <div class="text-center text-muted small">
                        Don't have an account? <a href="{{ route('register') }}" class="fw-semibold">Register here</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3 p-3 bg-light border text-muted small">
            <div class="fw-bold mb-1"><i class="bi bi-key me-1"></i> Quick Demo Access:</div>
            <div><strong>System Admin:</strong> admin@academiq.edu / password123</div>
            <div><strong>KUET Admin:</strong> admin@kuet.ac.bd / password123</div>
            <div><strong>Teacher:</strong> tariqul@cse.kuet.ac.bd / password123</div>
            <div><strong>Employer:</strong> hr@brainstation23.com / password123</div>
        </div>
    </div>
</div>
@endsection
