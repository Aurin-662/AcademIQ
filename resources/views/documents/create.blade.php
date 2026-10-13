@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-patch-plus me-2"></i>Issue Academic Certificate</h5>
                    <span class="badge bg-light text-primary">Institution Authority</span>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('documents.store') }}">
                    @csrf
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Institution Code</label>
                            <input type="text" name="institution_code" class="form-control" 
                                value="{{ auth()->user()->institution_code ?? old('institution_code') }}" 
                                {{ auth()->check() && auth()->user()->institution_code ? 'readonly' : '' }} required>
                            <div class="form-text">e.g. KUET, BUET</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Institution Name</label>
                            @php
                                $instName = old('institution_name');
                                if (auth()->check() && auth()->user()->institution_code) {
                                    $inst = \App\Models\Institution::where('code', auth()->user()->institution_code)->first();
                                    if ($inst) $instName = $inst->name;
                                }
                            @endphp
                            <input type="text" name="institution_name" class="form-control" 
                                value="{{ $instName ?? old('institution_name') }}" 
                                {{ auth()->check() && auth()->user()->institution_code ? 'readonly' : '' }} required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Student Full Name</label>
                            <input type="text" name="student_name" class="form-control" value="{{ old('student_name') }}" placeholder="e.g. Mohammad Tanvir Rahman" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Roll / Registration No</label>
                            <input type="text" name="roll_no" class="form-control" value="{{ old('roll_no') }}" placeholder="e.g. 2007001" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department / Program</label>
                            <input type="text" name="department" class="form-control" value="{{ old('department') }}" placeholder="e.g. Computer Science and Engineering" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Passing Year</label>
                            <input type="number" name="passing_year" class="form-control" value="{{ old('passing_year', date('Y')) }}" min="1980" max="2099" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Result / CGPA / Grade</label>
                            <input type="text" name="result_grade" class="form-control" value="{{ old('result_grade') }}" placeholder="e.g. 3.85 / 4.00" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                        <a href="{{ url('/') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-shield-lock-fill me-1"></i> Sign & Issue Certificate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection