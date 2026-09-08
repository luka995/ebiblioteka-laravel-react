<?php

use App\Services\Security\TurnstileService;
use Illuminate\Support\Facades\Http;

test('turnstile verify returns true on a successful Cloudflare response', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $service = new TurnstileService('test-secret');

    expect($service->verify('token', '127.0.0.1'))->toBeTrue();
});

test('turnstile verify accepts a matching action and hostname', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'action' => 'contact',
            'hostname' => 'demo.ebiblioteka.rs',
        ], 200),
    ]);

    $service = new TurnstileService('test-secret');

    expect($service->verify('token', '127.0.0.1', 'contact', ['demo.ebiblioteka.rs']))->toBeTrue();
});

test('turnstile verify rejects a mismatched action', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'action' => 'other',
            'hostname' => 'demo.ebiblioteka.rs',
        ], 200),
    ]);

    $service = new TurnstileService('test-secret');

    expect($service->verify('token', '127.0.0.1', 'contact', ['demo.ebiblioteka.rs']))->toBeFalse();
});

test('turnstile verify rejects an unapproved hostname', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'action' => 'contact',
            'hostname' => 'evil.example.com',
        ], 200),
    ]);

    $service = new TurnstileService('test-secret');

    expect($service->verify('token', '127.0.0.1', 'contact', ['demo.ebiblioteka.rs']))->toBeFalse();
});

test('turnstile verify returns false when Cloudflare reports success false', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
    ]);

    $service = new TurnstileService('test-secret');

    expect($service->verify('token', '127.0.0.1'))->toBeFalse();
});

test('turnstile verify returns false on an HTTP failure', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([], 500),
    ]);

    $service = new TurnstileService('test-secret');

    expect($service->verify('token', '127.0.0.1'))->toBeFalse();
});

test('turnstile verify fails closed when Cloudflare is unreachable', function () {
    Http::fake(function () {
        throw new RuntimeException('unreachable');
    });

    $service = new TurnstileService('test-secret');

    expect($service->verify('token', '127.0.0.1'))->toBeFalse();
});
