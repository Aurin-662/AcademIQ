<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications', ['items' => UserNotification::where('user_id', (string) $request->user()->getKey())->orderByDesc('created_at')->paginate(20)]);
    }

    /** Marks one (or all) of the CURRENT user's notifications read. Another user's id yields 404. */
    public function read(Request $request, ?string $id = null)
    {
        $mine = UserNotification::where('user_id', (string) $request->user()->getKey());
        if (! $id) {
            (clone $mine)->whereNull('read_at')->update(['read_at' => now()]);

            return redirect()->route('notifications');
        }
        $n = $mine->findOrFail($id);
        if (! $n->read_at) {
            $n->update(['read_at' => now()]);
        }

        // Follow only same-site links.
        return redirect($n->url && str_starts_with($n->url, url('/')) ? $n->url : route('notifications'));
    }
}
