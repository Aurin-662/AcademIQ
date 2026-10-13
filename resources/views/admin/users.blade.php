@extends('layouts.app')

@section('content')
<div class="py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>User & Authority Management</h3>
            <p class="text-secondary small mb-0">Approve pending institutions and faculty members to grant issuing privileges.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url('/admin/users?status=pending') }}" class="btn btn-outline-warning btn-sm {{ $status === 'pending' ? 'active' : '' }}">
                <i class="bi bi-hourglass-split me-1"></i> Pending Approvals
            </a>
            <a href="{{ url('/admin/users') }}" class="btn btn-outline-secondary btn-sm {{ !$status ? 'active' : '' }}">
                Show All
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Affiliation / Org</th>
                            <th>Issuing Approval</th>
                            <th>Faculty Verification</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $u)
                            <tr>
                                <td><strong>{{ $u->name }}</strong></td>
                                <td>{{ $u->email }}</td>
                                <td>
                                    <span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $u->role) }}</span>
                                </td>
                                <td>
                                    @if($u->institution_code)
                                        <span class="badge bg-info-subtle text-info border">Inst: {{ $u->institution_code }}</span>
                                    @elseif($u->organization)
                                        <span class="badge bg-light text-dark border">{{ $u->organization }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($u->approved)
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                    @else
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending Review</span>
                                    @endif
                                </td>
                                <td>
                                    @if($u->role === 'teacher')
                                        @php
                                            $teacherRec = $teachers->get($u->id);
                                        @endphp
                                        @if($teacherRec && $teacherRec->verified)
                                            <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified Faculty</span>
                                        @else
                                            <span class="badge bg-secondary">Unverified</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if(!$u->approved && in_array($u->role, ['institution_admin', 'teacher']))
                                            <form action="{{ url('/admin/users/' . $u->id . '/approve') }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm" title="Approve Issuer">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                        @endif

                                        @if($u->role === 'teacher')
                                            @php
                                                $tRec = $teachers->get($u->id);
                                            @endphp
                                            @if($tRec)
                                                <form action="{{ url('/admin/teachers/' . $tRec->teacher_id . '/verify') }}" method="POST" class="d-inline ms-1">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-info btn-sm" title="Toggle Faculty Verification">
                                                        {{ $tRec->verified ? 'Unverify' : 'Verify Faculty' }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endif

                                        @if($u->role !== 'system_admin')
                                            <form action="{{ url('/admin/users/' . $u->id . '/reject') }}" method="POST" class="d-inline ms-1" onsubmit="return confirm('Are you sure you want to remove this user?');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Reject / Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="p-3 border-top">
                    {{ $users->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
