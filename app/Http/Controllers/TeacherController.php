<?php

namespace App\Http\Controllers;

use App\Models\DocumentRecord;
use App\Models\Institution;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TeacherController extends Controller
{
    /**
     * Show form to issue a Recommendation Letter (LOR)
     */
    public function createLor()
    {
        $teacher = Teacher::where('user_id', auth()->id())->first();
        if (!$teacher) {
            return redirect('/')->with('error', 'Teacher profile not found.');
        }

        return view('lor.create', compact('teacher'));
    }

    /**
     * Store new Recommendation Letter (LOR)
     */
    public function storeLor(Request $request)
    {
        $request->validate([
            'recipient_name' => 'required|string|max:255',
            'purpose'        => 'required|string|max:500',
        ]);

        $teacher = Teacher::where('user_id', auth()->id())->first();
        if (!$teacher) {
            abort(403, 'Teacher profile not found.');
        }

        $year = date('Y');
        $code = strtoupper($teacher->institution_code ?: 'TCHR');
        $docId = 'LOR-' . $year . '-' . $code . '-' . strtoupper(Str::random(6));

        $hash = DocumentRecord::computeLorHash(
            $docId,
            $request->input('recipient_name'),
            $teacher->teacher_id,
            $request->input('purpose')
        );

        $doc = DocumentRecord::create([
            'document_id'    => $docId,
            'document_type'  => 'recommendation_letter',
            'issuer_type'    => 'teacher',
            'issuer_id'      => $teacher->teacher_id,
            'issuer_name'    => $teacher->name,
            'recipient_name' => $request->input('recipient_name'),
            'purpose'        => $request->input('purpose'),
            'roll_no'        => null,
            'department'     => $teacher->department,
            'passing_year'   => null,
            'result_grade'   => null,
            'issue_date'     => now(),
            'hash'           => $hash,
            'status'         => 'active',
        ]);

        $qr = QrCode::format('svg')->size(250)->generate(url('/verify/' . $docId));

        return view('documents.success', compact('doc', 'qr'));
    }

    /**
     * Public search for teachers by name, department, or institution
     */
    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));

        $query = Teacher::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('department', 'LIKE', "%{$q}%")
                    ->orWhere('institution_code', 'LIKE', "%{$q}%")
                    ->orWhere('teacher_id', 'LIKE', "%{$q}%");
            });
        }

        $teachers = $query->paginate(15);

        return view('teachers.search', compact('teachers', 'q'));
    }

    /**
     * Public profile of a teacher
     */
    public function show(Request $request, string $teacherId)
    {
        $teacher = Teacher::where('teacher_id', $teacherId)->firstOrFail();
        $institution = Institution::where('code', $teacher->institution_code)->first();

        // Check if there is a recipient filter query: "Did this teacher issue a letter to ___?"
        $searchRecipient = trim($request->query('recipient', ''));

        $query = DocumentRecord::where('issuer_id', $teacher->teacher_id)
            ->where('document_type', 'recommendation_letter');

        if ($searchRecipient !== '') {
            $query->where('recipient_name', 'LIKE', "%{$searchRecipient}%");
        }

        $letters = $query->orderBy('issue_date', 'desc')->paginate(10);

        return view('teachers.show', compact('teacher', 'institution', 'letters', 'searchRecipient'));
    }
}
