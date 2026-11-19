<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Services\Credentials\BulkImport;

class StudentController extends Controller
{
    /** Issuer form autofill (1.2): name + department from the student record. Throttled; no credential data. */
    public function lookup(string $ref)
    {
        $s = StudentReference::where('student_reference', $ref)->where('account_status', 'active')->first();
        if (! $s) {
            return response()->json(['found' => false], 404);
        }
        $dept = StudentProfile::where('student_reference', $ref)->value('department');

        return response()->json(['found' => true, 'name' => User::find($s->user_id)?->name, 'department' => $dept,
            'programme_code' => $dept ? BulkImport::resolve('programmes', $dept) : null, 'wallet_ready' => ! empty($s->wallet_public_key)]);
    }

    public function index()
    {
        return view('admin.students', ['students' => StudentReference::orderBy('created_at', 'desc')->paginate(25)]);
    }
}
