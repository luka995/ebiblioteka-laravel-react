<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnstileService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $endpoint = 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ) {}

    public function verify(string $token, ?string $ip = null, ?string $expectedAction = null, array $expectedHostnames = []): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post($this->endpoint, [
                    'secret' => $this->secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            if ($response->failed()) {
                Log::error('Cloudflare Turnstile verifikacija nije uspela (HTTP greška).', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            $result = $response->json();

            if (! ($result['success'] ?? false)) {
                return false;
            }

            if ($expectedAction !== null && ($result['action'] ?? null) !== $expectedAction) {
                return false;
            }

            if ($expectedHostnames !== [] && ! in_array($result['hostname'] ?? null, $expectedHostnames, true)) {
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Cloudflare Turnstile verifikacija nije uspela (izuzetak).', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
