<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'AcademIQ' }} — Academic Credential Verification & Fraud Detection</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .badge-role {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .main-content {
            flex: 1;
        }
        footer {
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }
    </style>
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
                <i class="bi bi-shield-check text-primary fs-4"></i>
                <span>Academ<span class="text-primary">IQ</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('verify*') ? 'active' : '' }}" href="{{ url('/verify') }}">
                            <i class="bi bi-search me-1"></i> Verify Document
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('teachers*') ? 'active' : '' }}" href="{{ url('/teachers/search') }}">
                            <i class="bi bi-person-badge me-1"></i> Teachers
                        </a>
                    </li>

                    @auth
                        @if(auth()->user()->role === 'employer')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('bulk') ? 'active' : '' }}" href="{{ url('/bulk') }}">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Bulk Verify
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('bulk/history') ? 'active' : '' }}" href="{{ url('/bulk/history') }}">
                                    <i class="bi bi-clock-history me-1"></i> Bulk History
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->role === 'institution_admin')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('documents/create') ? 'active' : '' }}" href="{{ route('documents.create') }}">
                                    <i class="bi bi-patch-plus me-1"></i> Issue Certificate
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('institution/analytics') ? 'active' : '' }}" href="{{ url('/institution/analytics') }}">
                                    <i class="bi bi-graph-up me-1"></i> Analytics
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->role === 'teacher')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('lor/create') ? 'active' : '' }}" href="{{ url('/lor/create') }}">
                                    <i class="bi bi-pen me-1"></i> Issue LOR
                                </a>
                            </li>
                            @php
                                $teacherRecord = \App\Models\Teacher::where('user_id', auth()->id())->first();
                            @endphp
                            @if($teacherRecord)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->is('teachers/'.$teacherRecord->teacher_id) ? 'active' : '' }}" href="{{ url('/teachers/'.$teacherRecord->teacher_id) }}">
                                        <i class="bi bi-person-vcard me-1"></i> My Profile
                                    </a>
                                </li>
                            @endif
                        @endif

                        @if(auth()->user()->role === 'system_admin')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('admin/analytics') ? 'active' : '' }}" href="{{ url('/admin/analytics') }}">
                                    <i class="bi bi-bar-chart-fill me-1"></i> System Analytics
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('admin/fraud') ? 'active' : '' }}" href="{{ url('/admin/fraud') }}">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Fraud Alerts
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('admin/users') ? 'active' : '' }}" href="{{ url('/admin/users') }}">
                                    <i class="bi bi-people me-1"></i> User Approvals
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->is('admin/documents') ? 'active' : '' }}" href="{{ url('/admin/documents') }}">
                                    <i class="bi bi-files me-1"></i> All Documents
                                </a>
                            </li>
                        @endif
                    @endauth
                </ul>

                <ul class="navbar-nav ms-auto align-items-lg-center">
                    @guest
                        <li class="nav-item">
                            <a class="nav-link {{ request()->is('login') ? 'active' : '' }}" href="{{ url('/login') }}">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm ms-lg-2 px-3" href="{{ url('/register') }}">
                                Register
                            </a>
                        </li>
                    @else
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-5"></i>
                                <span>{{ auth()->user()->name }}</span>
                                <span class="badge bg-secondary badge-role">{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                                @if(!auth()->user()->approved && in_array(auth()->user()->role, ['institution_admin', 'teacher']))
                                    <span class="badge bg-warning text-dark">Pending Approval</span>
                                @endif
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li>
                                    <div class="dropdown-item-text">
                                        <small class="text-muted d-block">{{ auth()->user()->email }}</small>
                                        @if(auth()->user()->institution_code)
                                            <small class="badge bg-info text-dark">Inst: {{ auth()->user()->institution_code }}</small>
                                        @endif
                                        @if(auth()->user()->organization)
                                            <small class="badge bg-light text-dark border">{{ auth()->user()->organization }}</small>
                                        @endif
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ url('/logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-box-arrow-right me-2"></i> Log Out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content py-4">
        <div class="container">
            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                    <div>{{ session('error') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div>{{ session('warning') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                    <div>{{ session('info') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please resolve the following errors:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="py-3 mt-auto">
        <div class="container text-center text-muted small">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <span>&copy; {{ date('Y') }} <strong>AcademIQ</strong> — High-Performance Academic Credential Verification System</span>
                <span class="text-secondary">Secured by HMAC-SHA256 & MongoDB Aggregation Analytics</span>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
