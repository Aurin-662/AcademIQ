<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Services\BackgroundChecks\BackgroundService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request, BackgroundService $svc)
    {
        $tasks = $svc->tasksFor($request->user())->orderBy('created_at', 'desc')->get();

        return view('external.dashboard', ['open' => $tasks->where('status', 'sent'), 'done' => $tasks->where('status', '!=', 'sent')]);
    }

    public function show(Request $request, BackgroundService $svc, string $id)
    {
        // Scoped lookup: tasks assigned to someone else (or another check type) return 404.
        $task = $svc->tasksFor($request->user())->findOrFail($id);

        return view('external.task', ['task' => $task, 'outcomes' => array_keys(BackgroundService::OUTCOMES[$task->verification_type])]);
    }

    public function respond(Request $request, BackgroundService $svc, string $id)
    {
        $task = $svc->tasksFor($request->user())->findOrFail($id);
        $d = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(BackgroundService::OUTCOMES[$task->verification_type]))],
            'reference' => ['nullable', 'string', 'max:80'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $svc->respond($request->user(), $id, $d['outcome'], $d['reference'] ?? null, $d['remarks'] ?? null);

        return redirect()->route('external.task', $id)->with('status', 'Response submitted. Thank you.');
    }
}
