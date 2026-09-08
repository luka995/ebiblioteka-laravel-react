<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactMessage;
use App\Services\Security\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class PublicContactController extends Controller
{
    public function __construct(private readonly TurnstileService $turnstile) {}

    public function create(): View
    {
        return view('public.contact');
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! $request->filled('turnstile_token') || ! $this->turnstile->verify((string) $request->input('turnstile_token'), $request->ip(), config('services.turnstile.action'), config('services.turnstile.hostnames'))) {
            return to_route('contact.create')
                ->with('contact_error', 'Нисмо успели да потврдимо да нисте робот. Покушајте поново.')
                ->withInput();
        }

        try {
            Mail::mailer(config('mail.transactional_mailer'))
                ->to(config('contact.recipient'))
                ->send((new ContactMessage($validated))->locale(app()->getLocale())->replyTo($validated['email'], $validated['name']));
        } catch (Throwable $e) {
            Log::error('Kontakt poruka nije poslata.', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return to_route('contact.create')
                ->with('contact_error', 'Дошло је до грешке приликом слања поруке. Покушајте поново.')
                ->withInput();
        }

        return to_route('contact.create')->with('contact_sent', true);
    }
}
