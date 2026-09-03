<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactMessage;
use App\Support\PublicTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PublicContactController extends Controller
{
    public function create(): View
    {
        return view(PublicTheme::view('public.contact'));
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Mail::to(config('contact.recipient'))
            ->send((new ContactMessage($validated))->replyTo($validated['email'], $validated['name']));

        return to_route('contact.create')->with('contact_sent', true);
    }
}
