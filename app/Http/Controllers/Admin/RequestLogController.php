<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\VerificationRequest;

/** 5.3: request and consent log (metadata only; no credential or proof data exists in these records). */
class RequestLogController extends Controller
{
    public function index()
    {
        $requests = VerificationRequest::orderBy('created_at', 'desc')->paginate(25);
        $consents = ConsentRecord::whereIn('request_id', $requests->pluck('request_id')->all())->get()->keyBy('request_id');

        return view('admin.requests', ['requests' => $requests, 'consents' => $consents]);
    }
}
