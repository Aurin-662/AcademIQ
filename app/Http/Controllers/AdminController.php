<?php

namespace App\Http\Controllers;

use App\Models\DocumentRecord;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    /**
     * List users and approval requests
     */
    public function users(Request $request)
    {
        $role = $request->query('role');
        $status = $request->query('status'); // 'pending' or 'approved'

        $query = User::query();

        if ($role) {
            $query->where('role', $role);
        }
        if ($status === 'pending') {
            $query->where('approved', false);
        } elseif ($status === 'approved') {
            $query->where('approved', true);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        // Also fetch teachers to see faculty verification statuses
        $teachers = Teacher::all()->keyBy('user_id');

        return view('admin.users', compact('users', 'teachers', 'role', 'status'));
    }

    /**
     * Approve user
     */
    public function approveUser(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['approved' => true]);

        return back()->with('success', "User {$user->name} ({$user->email}) approved successfully.");
    }

    /**
     * Reject / delete user
     */
    public function rejectUser(int $id)
    {
        $user = User::findOrFail($id);
        $name = $user->name;

        // If teacher, remove teacher mongo profile as well
        if ($user->role === 'teacher') {
            Teacher::where('user_id', $user->id)->delete();
        }

        $user->delete();

        return back()->with('info', "User {$name} was rejected and removed.");
    }

    /**
     * Toggle faculty verified status for a teacher
     */
    public function toggleTeacherVerified(string $teacherId)
    {
        $teacher = Teacher::where('teacher_id', $teacherId)->firstOrFail();
        $teacher->verified = !$teacher->verified;
        $teacher->save();

        $statusText = $teacher->verified ? 'marked as Verified Faculty' : 'unmarked from Verified Faculty';
        return back()->with('success', "Teacher {$teacher->name} {$statusText}.");
    }

    /**
     * List and manage documents
     */
    public function documents(Request $request)
    {
        $q = $request->query('q');
        $status = $request->query('status');
        $type = $request->query('type');

        $query = DocumentRecord::query();

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('document_id', 'LIKE', "%{$q}%")
                    ->orWhere('recipient_name', 'LIKE', "%{$q}%")
                    ->orWhere('roll_no', 'LIKE', "%{$q}%")
                    ->orWhere('issuer_id', 'LIKE', "%{$q}%");
            });
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($type) {
            $query->where('document_type', $type);
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('admin.documents', compact('documents', 'q', 'status', 'type'));
    }

    /**
     * Revoke document with reason and invalidate cache
     */
    public function revokeDocument(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $doc = DocumentRecord::where('document_id', $id)->firstOrFail();
        $doc->status = 'revoked';
        $doc->revocation_reason = $request->input('reason');
        $doc->revoked_at = now();
        $doc->revoked_by = auth()->user()->email;
        $doc->save();

        // Invalidate cache immediately
        Cache::forget('doc_' . $doc->document_id);

        return back()->with('success', "Document {$doc->document_id} was officially revoked.");
    }

    /**
     * Admin Fraud Alerts & Detection Review Center
     */
    public function fraud(Request $request)
    {
        $status = $request->query('status'); // pending_review, confirmed, dismissed
        $type   = $request->query('type');   // duplicate_roll, near_duplicate_name, hash_mismatch, anomalous_verification_pattern

        $query = \App\Models\FraudFlag::query();

        if ($status) {
            $query->where('status', $status);
        }
        if ($type) {
            $query->where('flag_type', $type);
        }

        $flags = $query->orderBy('detected_at', 'desc')->paginate(20);

        return view('admin.fraud', compact('flags', 'status', 'type'));
    }

    /**
     * Confirm a fraud flag and flag the document
     */
    public function confirmFraud(string $id)
    {
        $flag = \App\Models\FraudFlag::where('_id', $id)->orWhere('id', $id)->firstOrFail();
        $flag->status = 'confirmed';
        $flag->reviewed_by = auth()->user()->email;
        $flag->reviewed_at = now();
        $flag->save();

        // Flag the document and clear cache
        if ($flag->certificate_id) {
            $doc = DocumentRecord::where('document_id', $flag->certificate_id)->first();
            if ($doc) {
                $doc->status = 'flagged';
                $doc->save();
                Cache::forget('doc_' . $doc->document_id);
            }
        }

        return back()->with('success', "Fraud flag #{$id} confirmed. Document marked as FLAGGED (Under Review).");
    }

    /**
     * Dismiss a fraud flag
     */
    public function dismissFraud(string $id)
    {
        $flag = \App\Models\FraudFlag::where('_id', $id)->orWhere('id', $id)->firstOrFail();
        $flag->status = 'dismissed';
        $flag->reviewed_by = auth()->user()->email;
        $flag->reviewed_at = now();
        $flag->save();

        return back()->with('info', "Fraud flag #{$id} was dismissed.");
    }
}
