@extends('layouts.app')

@section('content')
<div class="py-3">
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <h3 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Academic Faculty Directory</h3>
            <p class="text-secondary small mb-0">Browse and search registered university faculty members and their public issued credentials</p>
        </div>
        <div class="col-md-5 mt-3 mt-md-0">
            <form action="{{ url('/teachers/search') }}" method="GET">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search by name, dept, or university..." value="{{ $q }}">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($teachers->isEmpty())
        <div class="card p-5 text-center text-muted border-0 shadow-sm">
            <i class="bi bi-person-slash fs-1 mb-2"></i>
            <h5>No faculty members found</h5>
            <p class="small mb-3">Try adjusting your search query or clear the filter.</p>
            <div>
                <a href="{{ url('/teachers/search') }}" class="btn btn-outline-primary btn-sm">Clear Search</a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach($teachers as $teacher)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 p-3 shadow-sm border-0">
                        <div class="d-flex align-items-start gap-3">
                            <div class="p-3 bg-primary-subtle text-primary rounded-circle">
                                <i class="bi bi-person-workspace fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-0">
                                    <a href="{{ url('/teachers/' . $teacher->teacher_id) }}" class="text-decoration-none text-dark">
                                        {{ $teacher->name }}
                                    </a>
                                </h5>
                                <div class="text-muted small">{{ $teacher->designation }}</div>
                                <div class="text-secondary small mt-1">
                                    <i class="bi bi-building me-1"></i>{{ $teacher->department }}, {{ $teacher->institution_code }}
                                </div>

                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    @if($teacher->verified)
                                        <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified Faculty</span>
                                    @else
                                        <span class="badge bg-secondary">Unverified</span>
                                    @endif

                                    <a href="{{ url('/teachers/' . $teacher->teacher_id) }}" class="btn btn-sm btn-outline-primary">
                                        View Profile &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $teachers->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
