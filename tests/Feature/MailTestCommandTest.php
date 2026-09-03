<?php

use Illuminate\Support\Facades\Mail;

test('mail:test sends a message with the configured mailer and reports success', function () {
    Mail::fake();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com', 'mail.mailers.smtp.port' => 587]);

    $this->artisan('mail:test', ['to' => 'someone@example.com'])
        ->expectsOutputToContain('smtp.example.com:587')
        ->expectsOutputToContain('Message accepted')
        ->assertSuccessful();
});

test('mail:test warns when mail only goes to the log', function () {
    config(['mail.default' => 'log']);

    $this->artisan('mail:test', ['to' => 'someone@example.com'])
        ->expectsOutputToContain('nothing will reach a real inbox')
        ->assertSuccessful();
});
