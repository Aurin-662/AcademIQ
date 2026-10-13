@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i>Document Issued Successfully</h5>
                    <span class="badge bg-light text-success text-uppercase">{{ $doc->status ?? 'active' }}</span>
                </div>
            </div>
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6">
                        {{ $doc->document_id }}
                    </span>
                </div>

                <div class="p-3 bg-white rounded border d-inline-block shadow-sm my-2">
                    {!! $qr !!}
                </div>
                <div class="text-muted small mt-1">Scan SVG QR Code to verify authenticity instantly</div>

                <div class="table-responsive text-start mt-4">
                    <table class="table table-bordered table-sm align-middle">
                        <tbody>
                            <tr>
                                <th class="table-light w-35">Recipient</th>
                                <td><strong>{{ $doc->recipient_name }}</strong></td>
                            </tr>
                            @if($doc->roll_no)
                            <tr>
                                <th class="table-light">Roll / ID</th>
                                <td>{{ $doc->roll_no }}</td>
                            </tr>
                            @endif
                            @if($doc->department)
                            <tr>
                                <th class="table-light">Department</th>
                                <td>{{ $doc->department }}</td>
                            </tr>
                            @endif
                            @if($doc->passing_year)
                            <tr>
                                <th class="table-light">Passing Year</th>
                                <td>{{ $doc->passing_year }}</td>
                            </tr>
                            @endif
                            @if($doc->result_grade)
                            <tr>
                                <th class="table-light">Grade / CGPA</th>
                                <td>{{ $doc->result_grade }}</td>
                            </tr>
                            @endif
                            @if($doc->purpose)
                            <tr>
                                <th class="table-light">Purpose</th>
                                <td>{{ $doc->purpose }}</td>
                            </tr>
                            @endif
                            <tr>
                                <th class="table-light">Issuer</th>
                                <td>{{ $doc->issuer_name }} ({{ $doc->issuer_id }})</td>
                            </tr>
                            <tr>
                                <th class="table-light">Cryptographic Hash</th>
                                <td><code class="text-break small">{{ $doc->hash }}</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                    <a href="{{ url('/verify/' . $doc->document_id) }}" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Public Verify Link
                    </a>
                    <a href="{{ url('/documents/' . $doc->document_id . '/print') }}" class="btn btn-dark" target="_blank">
                        <i class="bi bi-printer me-1"></i> Print / Certificate View
                    </a>
                    @if($doc->document_type === 'recommendation_letter')
                        <a href="{{ url('/lor/create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Issue Another LOR
                        </a>
                    @else
                        <a href="{{ route('documents.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Issue Another Certificate
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection