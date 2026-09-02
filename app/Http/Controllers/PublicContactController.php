<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Support\PublicTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PublicContactController extends Controller
{
    public function create(): View
    {
        return view(PublicTheme::view('public.contact'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'organization' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Mail::to(config('contact.recipient'))
            ->send((new ContactMessage($validated))->replyTo($validated['email'], $validated['name']));

        return to_route('contact.create')->with('contact_sent', true);
    }
}
