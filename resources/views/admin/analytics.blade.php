@extends('layouts.app')

@section('content')
<div class="py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-graph-up text-primary me-2"></i>System-Wide Verification & Fraud Analytics</h3>
            <p class="text-secondary small mb-0">Multi-stage MongoDB aggregation pipelines computing live integrity metrics across all universities.</p>
        </div>
        <div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                <i class="bi bi-lightning-charge-fill me-1"></i> 60s High-Performance Aggregation Cache
            </span>
        </div>
    </div>

    <!-- Row 1: Timeline & Result Breakdown -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-range me-2 text-primary"></i>Daily Verification Volume (Last 30 Days)</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $perDay['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 280px;">
                        <canvas id="chartPerDay"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$match &rarr; $group &rarr; $sort</code></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2 text-success"></i>Verification Outcomes</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $breakdown['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 280px;">
                        <canvas id="chartBreakdown"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$group by result</code></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Top Institutions & Hourly Activity -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-bank me-2 text-warning"></i>Top 10 Institutions by Verification Count</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $topInst['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 280px;">
                        <canvas id="chartTopInst"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$lookup &rarr; $unwind &rarr; $group &rarr; $sort</code></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-info"></i>Hourly Verification Distribution (Asia/Dhaka)</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $byHour['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 280px;">
                        <canvas id="chartByHour"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$group by $hour</code></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Fraud Flags & Document Types -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Fraud Flag Detection by Category</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $fraud['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 260px;">
                        <canvas id="chartFraud"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$group in fraud_flags</code></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-files me-2 text-dark"></i>Most Verified Document Types</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $docTypes['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 260px;">
                        <canvas id="chartDocTypes"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$lookup &rarr; $group by doc_type</code></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Verifications Per Day
        const ctxPerDay = document.getElementById('chartPerDay').getContext('2d');
        new Chart(ctxPerDay, {
            type: 'line',
            data: {
                labels: {!! json_encode($perDay['labels']) !!},
                datasets: [{
                    label: 'Verifications',
                    data: {!! json_encode($perDay['data']) !!},
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        // 2. Results Breakdown
        const ctxBreakdown = document.getElementById('chartBreakdown').getContext('2d');
        new Chart(ctxBreakdown, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($breakdown['labels']) !!},
                datasets: [{
                    data: {!! json_encode($breakdown['data']) !!},
                    backgroundColor: ['#10b981', '#ef4444', '#64748b', '#0f172a']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // 3. Top Institutions
        const ctxTopInst = document.getElementById('chartTopInst').getContext('2d');
        new Chart(ctxTopInst, {
            type: 'bar',
            data: {
                labels: {!! json_encode($topInst['labels']) !!},
                datasets: [{
                    label: 'Audited Credentials',
                    data: {!! json_encode($topInst['data']) !!},
                    backgroundColor: '#f59e0b',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        // 4. Verifications by Hour
        const ctxByHour = document.getElementById('chartByHour').getContext('2d');
        new Chart(ctxByHour, {
            type: 'bar',
            data: {
                labels: {!! json_encode($byHour['labels']) !!},
                datasets: [{
                    label: 'Requests',
                    data: {!! json_encode($byHour['data']) !!},
                    backgroundColor: '#06b6d4',
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        // 5. Fraud Flags
        const ctxFraud = document.getElementById('chartFraud').getContext('2d');
        new Chart(ctxFraud, {
            type: 'bar',
            data: {
                labels: {!! json_encode($fraud['labels']) !!},
                datasets: [{
                    label: 'Flags Detected',
                    data: {!! json_encode($fraud['data']) !!},
                    backgroundColor: '#e11d48',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        // 6. Document Types
        const ctxDocTypes = document.getElementById('chartDocTypes').getContext('2d');
        new Chart(ctxDocTypes, {
            type: 'pie',
            data: {
                labels: {!! json_encode($docTypes['labels']) !!},
                datasets: [{
                    data: {!! json_encode($docTypes['data']) !!},
                    backgroundColor: ['#6366f1', '#14b8a6']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    });
</script>
@endpush
@endsection
