<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $contact = ContactMessage::create($validated);

        return response()->json([
            'message' => 'Pesan kamu berhasil terkirim. Tim TUTORKU akan segera menghubungi kamu.',
            'data' => $contact,
        ], 201);
    }
}
