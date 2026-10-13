<?php

namespace App\Http\Controllers;

use App\Models\DocumentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentController extends Controller
{
    public function create()
    {
        $user = auth()->user();
        $institution = null;
        if ($user && $user->institution_code) {
            $institution = \App\Models\Institution::where('code', $user->institution_code)->first();
        }
        return view('documents.create', compact('institution'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'student_name' => 'required|string',
            'roll_no'      => 'required|string',
            'department'   => 'required|string',
            'passing_year' => 'required|integer',
            'result_grade' => 'required|string',
        ]);

        $user = auth()->user();
        $code = ($user && $user->institution_code)
            ? strtoupper($user->institution_code)
            : strtoupper($request->input('institution_code', 'INST'));

        $institution = \App\Models\Institution::where('code', $code)->first();
        $instName = $institution ? $institution->name : ($request->input('institution_name') ?? $code);

        $docId = 'CERT-' . $v['passing_year'] . '-' . $code . '-' . strtoupper(Str::random(6));

        $hash = DocumentRecord::computeHash(
            $docId, $v['student_name'], $code, $v['roll_no'], $v['passing_year']
        );

        // Pre-issue Fraud check hook (Phase 6)
        if (class_exists(\App\Services\FraudService::class)) {
            try {
                app(\App\Services\FraudService::class)->checkNewCertificate([
                    'document_id'    => $docId,
                    'recipient_name' => $v['student_name'],
                    'roll_no'        => $v['roll_no'],
                    'issuer_id'      => $code,
                    'passing_year'   => (int) $v['passing_year'],
                    'department'     => $v['department'],
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Fraud check failed during certificate issue: ' . $e->getMessage());
            }
        }

        $doc = DocumentRecord::create([
            'document_id'    => $docId,
            'document_type'  => 'certificate',
            'issuer_type'    => 'institution',
            'issuer_id'      => $code,
            'issuer_name'    => $instName,
            'recipient_name' => $v['student_name'],
            'roll_no'        => $v['roll_no'],
            'department'     => $v['department'],
            'passing_year'   => (int) $v['passing_year'],
            'result_grade'   => $v['result_grade'],
            'issue_date'     => now(),
            'hash'           => $hash,
            'status'         => 'active',
        ]);

        $qr = QrCode::format('svg')->size(250)->generate(url('/verify/' . $docId));

        return view('documents.success', compact('doc', 'qr'));
    }

    /**
     * Printable certificate / LOR view with print stylesheet and QR code
     */
    public function print(string $id)
    {
        $doc = DocumentRecord::where('document_id', $id)->firstOrFail();

        // Access check: issuer or system_admin
        if (!auth()->check()) {
            return redirect()->guest('/login')->with('error', 'Please log in to view the printable certificate.');
        }

        $user = auth()->user();
        $isAuthorized = false;

        if ($user->isSystemAdmin()) {
            $isAuthorized = true;
        } elseif ($doc->issuer_type === 'institution' && $user->isInstitutionAdmin() && $user->institution_code === $doc->issuer_id) {
            $isAuthorized = true;
        } elseif ($doc->issuer_type === 'teacher' && $user->isTeacher()) {
            $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
            if ($teacher && $teacher->teacher_id === $doc->issuer_id) {
                $isAuthorized = true;
            }
        }

        if (!$isAuthorized) {
            abort(403, 'Unauthorized. Only the issuing authority or a system administrator can access the printable document.');
        }

        $qr = QrCode::format('svg')->size(160)->generate(url('/verify/' . $doc->document_id));

        return view('documents.print', compact('doc', 'qr'));
    }
}