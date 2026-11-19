<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function contact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        ContactMessage::create($data + ['ip' => $request->ip()]);

        return back()->with('status', 'Message received. We will reply by email.');
    }
}
