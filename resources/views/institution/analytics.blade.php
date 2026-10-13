@extends('layouts.app')

@section('content')
<div class="py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-bank text-primary me-2"></i>{{ $instCode }} Credential Verification Analytics</h3>
            <p class="text-secondary small mb-0">Live verification performance and integrity metrics for credentials issued by <strong>{{ $instCode }}</strong>.</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 fs-6">
                Institution Code: {{ $instCode }}
            </span>
        </div>
    </div>

    <!-- Row 1: Timeline & Breakdown -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-range me-2 text-primary"></i>Daily Verifications for {{ $instCode }} (Last 30 Days)</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $perDay['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 280px;">
                        <canvas id="chartPerDay"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$match &rarr; $lookup &rarr; $group</code></div>
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

    <!-- Row 2: Hourly Distribution & Document Types -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-info"></i>Verification Distribution by Hour of Day</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $byHour['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 260px;">
                        <canvas id="chartByHour"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$group by $hour</code></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-files me-2 text-dark"></i>Credential Categories</h6>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $docTypes['query_time_ms'] ?? 0 }} ms</small>
                </div>
                <div class="card-body p-4">
                    <div style="height: 260px;">
                        <canvas id="chartDocTypes"></canvas>
                    </div>
                    <div class="text-end text-muted small mt-2">MongoDB Pipeline: <code>$group by doc_type</code></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Daily Verifications
        const ctxPerDay = document.getElementById('chartPerDay').getContext('2d');
        new Chart(ctxPerDay, {
            type: 'line',
            data: {
                labels: {!! json_encode($perDay['labels']) !!},
                datasets: [{
                    label: 'Verifications',
                    data: {!! json_encode($perDay['data']) !!},
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.1)',
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

        // Breakdown
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

        // By Hour
        const ctxByHour = document.getElementById('chartByHour').getContext('2d');
        new Chart(ctxByHour, {
            type: 'bar',
            data: {
                labels: {!! json_encode($byHour['labels']) !!},
                datasets: [{
                    label: 'Requests',
                    data: {!! json_encode($byHour['data']) !!},
                    backgroundColor: '#0ea5e9',
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

        // Doc Types
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
