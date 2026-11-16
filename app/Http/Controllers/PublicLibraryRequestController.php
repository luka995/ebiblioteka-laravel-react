<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLibraryRequestRequest;
use App\Mail\LibraryRequestReceived;
use App\Models\LibraryRequest;
use App\Models\User;
use App\Services\GlobalSettings;
use App\Services\Security\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class PublicLibraryRequestController extends Controller
{
    public function __construct(
        private readonly TurnstileService $turnstile,
        private readonly GlobalSettings $settings,
    ) {}

    public function create(): View
    {
        return view('public.library-requests.create');
    }

    public function store(StoreLibraryRequestRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! $request->filled('turnstile_token') || ! $this->turnstile->verify((string) $request->input('turnstile_token'), $request->ip(), config('services.turnstile.library_request_action'), config('services.turnstile.hostnames'))) {
            return to_route('library-requests.create')
                ->with('library_request_error', 'Нисмо успели да потврдимо да нисте робот. Покушајте поново.')
                ->withInput();
        }

        unset($validated['turnstile_token']);

        $libraryRequest = LibraryRequest::create($validated);

        $this->notifySuperAdmins($libraryRequest);

        return to_route('library-requests.create')->with('library_request_sent', true);
    }

    private function notifySuperAdmins(LibraryRequest $libraryRequest): void
    {
        $emails = User::query()
            ->whereIn('id', $this->settings->superAdminIds())
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            $emails = array_values(array_filter((array) config('contact.recipient', [])));
        }

        if ($emails === []) {
            Log::warning('Zahtev za otvaranje biblioteke nema primaoca.', [
                'library_request_id' => $libraryRequest->id,
            ]);

            return;
        }

        try {
            $mail = (new LibraryRequestReceived($libraryRequest))
                ->locale('sr-Cyrl')
                ->replyTo($libraryRequest->contact_email, $libraryRequest->contact_name);

            Mail::mailer(config('mail.transactional_mailer'))
                ->to($emails)
                ->queue($mail);
        } catch (Throwable $e) {
            Log::error('Mejl o zahtevu za otvaranje biblioteke nije poslat.', [
                'library_request_id' => $libraryRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
