<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Document — {{ $doc->document_id }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Times New Roman', Times, serif;
            color: #0f172a;
        }
        .cert-container {
            max-width: 900px;
            margin: 30px auto;
            background: #ffffff;
            padding: 50px 60px;
            border: 12px double #1e3a8a;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .cert-header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .institution-title {
            font-size: 2.2rem;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .cert-type {
            font-size: 1.4rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 10px;
            color: #475569;
        }
        .recipient-name {
            font-size: 2rem;
            font-weight: bold;
            color: #0f172a;
            text-decoration: underline;
            margin: 20px 0;
            text-align: center;
        }
        .cert-body {
            font-size: 1.2rem;
            line-height: 1.8;
            text-align: justify;
        }
        .cert-footer {
            margin-top: 50px;
            padding-top: 20px;
        }
        .signature-line {
            border-top: 1px solid #334155;
            width: 220px;
            text-align: center;
            padding-top: 5px;
            font-size: 0.95rem;
        }
        .hash-code {
            font-family: monospace;
            font-size: 0.75rem;
            color: #64748b;
            word-break: break-all;
        }
        @media print {
            body {
                background: none;
                margin: 0;
            }
            .cert-container {
                box-shadow: none;
                margin: 0 auto;
                max-width: 100%;
                border: 8px double #1e3a8a;
                padding: 40px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container text-center my-3 no-print">
        <button onclick="window.print()" class="btn btn-primary btn-lg px-4 me-2">
            Print / Save as PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-lg">
            Close
        </button>
    </div>

    <div class="cert-container">
        <div class="cert-header">
            <div class="institution-title">{{ $doc->issuer_name }}</div>
            @if($doc->document_type === 'recommendation_letter')
                <div class="cert-type">Letter of Recommendation</div>
            @else
                <div class="cert-type">Official Academic Transcript & Certificate</div>
            @endif
            <div class="small text-muted mt-1">Document ID: <strong>{{ $doc->document_id }}</strong></div>
        </div>

        <div class="cert-body">
            @if($doc->document_type === 'recommendation_letter')
                <p>To Whom It May Concern,</p>
                <p>
                    This is to certify that <strong>{{ $doc->recipient_name }}</strong> is known to me in my academic capacity. 
                    I write this recommendation regarding: <em>{{ $doc->purpose }}</em>.
                </p>
                <p>
                    Throughout the period of association, the candidate demonstrated exemplary academic dedication, strong problem-solving skills, and commendable integrity.
                </p>
            @else
                <p class="text-center">This is to certify that</p>
                <div class="recipient-name">{{ $doc->recipient_name }}</div>
                <p class="text-center">
                    bearing Roll / Registration Number <strong>{{ $doc->roll_no }}</strong> has satisfactorily completed the requirements of the degree in 
                    <strong>{{ $doc->department }}</strong> in the year <strong>{{ $doc->passing_year }}</strong> and achieved a result of 
                    <strong>{{ $doc->result_grade }}</strong>.
                </p>
            @endif
        </div>

        <div class="cert-footer">
            <div class="d-flex justify-content-between align-items-end">
                <div class="text-start">
                    <div class="p-2 border bg-white d-inline-block">
                        {!! $qr !!}
                    </div>
                    <div class="small text-muted mt-1">Scan QR to verify live authenticity</div>
                </div>

                <div class="text-end">
                    <div class="signature-line ms-auto">
                        <strong>{{ $doc->issuer_name }}</strong><br>
                        <span class="small text-muted">Authorized Signatory</span><br>
                        <span class="small text-muted">Issued: {{ is_string($doc->issue_date) ? $doc->issue_date : $doc->issue_date->format('F d, Y') }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-center">
                <span class="text-muted small">Cryptographic Integrity Hash (HMAC-SHA256):</span><br>
                <span class="hash-code">{{ $doc->hash }}</span>
            </div>
        </div>
    </div>
</body>
</html>
