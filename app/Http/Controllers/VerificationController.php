<?php

namespace App\Http\Controllers;

use App\Services\VerificationService;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    protected VerificationService $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Show verification search form or redirect if ID query passed
     */
    public function index(Request $request)
    {
        if ($request->filled('id')) {
            $docId = trim($request->input('id'));
            return redirect('/verify/' . urlencode($docId));
        }

        return view('verify.index');
    }

    /**
     * Public verification page (QR target)
     */
    public function verify(Request $request, string $documentId)
    {
        $cleanId = trim($documentId);
        $result = $this->verificationService->verify($cleanId, $request, 'single');

        return view('verify.show', compact('result'));
    }

    /**
     * JSON verification endpoint (used for k6 load test)
     */
    public function verifyApi(Request $request, string $documentId)
    {
        $cleanId = trim($documentId);
        $result = $this->verificationService->verify($cleanId, $request, 'single');

        $status = match ($result['result']) {
            'genuine'   => 200,
            'tampered'  => 422,
            'revoked'   => 410,
            'not_found' => 404,
            default     => 200,
        };

        return response()->json($result, $status);
    }
}
