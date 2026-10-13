@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-md-7 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white py-3 text-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2"></i>Create an AcademIQ Account</h5>
                <small class="text-secondary">Join as an Employer, Academic Institution, or Faculty Member</small>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('register') }}" id="registerForm">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Account Role</label>
                        <select name="role" id="roleSelect" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="employer" {{ old('role') === 'employer' ? 'selected' : '' }}>Employer / Recruiter (Instant Verification Access)</option>
                            <option value="teacher" {{ old('role') === 'teacher' ? 'selected' : '' }}>Faculty / Teacher (Requires Admin Approval)</option>
                            <option value="institution_admin" {{ old('role') === 'institution_admin' ? 'selected' : '' }}>Institution Admin (Requires Admin Approval)</option>
                        </select>
                        <div class="form-text" id="roleHelp">
                            Employers are approved automatically. Institution Admins & Faculty require System Admin approval before issuing documents.
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label for="name" class="form-label fw-semibold">Full Name</label>
                            <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="e.g. Dr. Kazi Mostafa or John Doe" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="name@domain.edu or name@company.com" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Role-specific sections --}}
                    {{-- Employer Section --}}
                    <div id="employerFields" class="p-3 bg-light rounded border mb-3">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-building me-1"></i> Employer Details</h6>
                        <label for="organization" class="form-label fw-semibold">Company / Organization Name</label>
                        <input id="organization" type="text" class="form-control @error('organization') is-invalid @enderror" name="organization" value="{{ old('organization') }}" placeholder="e.g. Google, Brain Station 23, Robi">
                        @error('organization')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Institution Admin Section --}}
                    <div id="institutionFields" class="p-3 bg-light rounded border mb-3 d-none">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-bank me-1"></i> Institution Details</h6>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label for="inst_code" class="form-label fw-semibold">Institution Code</label>
                                <input id="inst_code" type="text" class="form-control @error('institution_code') is-invalid @enderror" name="institution_code" value="{{ old('institution_code') }}" placeholder="e.g. KUET">
                                @error('institution_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-7">
                                <label for="inst_name" class="form-label fw-semibold">Institution Full Name</label>
                                <input id="inst_name" type="text" class="form-control @error('institution_name') is-invalid @enderror" name="institution_name" value="{{ old('institution_name') }}" placeholder="e.g. Khulna University of Engineering & Technology">
                                @error('institution_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Teacher Section --}}
                    <div id="teacherFields" class="p-3 bg-light rounded border mb-3 d-none">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-mortarboard me-1"></i> Faculty Information</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Institution Code</label>
                                <input type="text" class="form-control" id="teacher_institution_code" name="teacher_institution_code" value="{{ old('institution_code') }}" placeholder="e.g. KUET">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Department</label>
                                <input type="text" class="form-control @error('department') is-invalid @enderror" name="department" value="{{ old('department') }}" placeholder="e.g. CSE">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Designation</label>
                                <input type="text" class="form-control @error('designation') is-invalid @enderror" name="designation" value="{{ old('designation') }}" placeholder="e.g. Professor">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="password-confirm" class="form-label fw-semibold">Confirm Password</label>
                            <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Register Account
                        </button>
                    </div>

                    <div class="text-center text-muted small">
                        Already have an account? <a href="{{ route('login') }}" class="fw-semibold">Sign In here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('roleSelect');
        const employerFields = document.getElementById('employerFields');
        const institutionFields = document.getElementById('institutionFields');
        const teacherFields = document.getElementById('teacherFields');
        const teacherInstCode = document.getElementById('teacher_institution_code');
        const instCode = document.getElementById('inst_code');

        function updateRoleFields() {
            const role = roleSelect.value;
            employerFields.classList.add('d-none');
            institutionFields.classList.add('d-none');
            teacherFields.classList.add('d-none');

            if (role === 'employer') {
                employerFields.classList.remove('d-none');
            } else if (role === 'institution_admin') {
                institutionFields.classList.remove('d-none');
            } else if (role === 'teacher') {
                teacherFields.classList.remove('d-none');
            }
        }

        // Before submit, synchronize institution_code field
        document.getElementById('registerForm').addEventListener('submit', function() {
            if (roleSelect.value === 'teacher' && teacherInstCode.value) {
                instCode.value = teacherInstCode.value;
            }
        });

        roleSelect.addEventListener('change', updateRoleFields);
        updateRoleFields();
    });
</script>
@endpush
@endsection
