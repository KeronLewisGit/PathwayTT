<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('a new user registers, is asked to verify, and lands on an empty dashboard after verifying', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Real Tester',
        'email' => 'real.tester@example.com',
        'password' => 'a-strong-password-1',
        'password_confirmation' => 'a-strong-password-1',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'real.tester@example.com')->firstOrFail();
    Notification::assertSentTo($user, VerifyEmail::class);

    // Unverified: in the app already, with a persistent reminder.
    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Please verify your email')
        ->assertSee('Upload your resume');

    // Simulate clicking the emailed link.
    $link = (new VerifyEmail)->toMail($user)->actionUrl;
    $this->actingAs($user)->get($link)->assertRedirectContains('/dashboard');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('Please verify your email');
});

test('on a test instance the verify screen points testers at the captured mailbox or shows the link', function () {
    $user = User::factory()->unverified()->create();

    // Production-like: nothing extra.
    config(['app.demo_data' => false, 'app.demo_mail_ui' => null, 'mail.default' => 'smtp']);
    $this->actingAs($user)->get('/verify-email')
        ->assertOk()
        ->assertDontSee('test instance')
        ->assertDontSee('verify-email-link');

    // Test instance with Mailpit: link to the shared inbox.
    config(['app.demo_data' => true, 'app.demo_mail_ui' => 'http://localhost:8025', 'mail.default' => 'smtp']);
    $this->actingAs($user)->get('/verify-email')
        ->assertOk()
        ->assertSee('http://localhost:8025')
        ->assertDontSee('verify-email-link');

    // Test instance with mail going to a log file: show the link itself.
    config(['app.demo_data' => true, 'app.demo_mail_ui' => null, 'mail.default' => 'log']);
    $response = $this->actingAs($user)->get('/verify-email');
    $response->assertOk()->assertSee('verify-email-link');

    preg_match('~id="verify-email-link" href="([^"]+)"~', $response->getContent(), $m);
    $this->actingAs($user)->get(html_entity_decode($m[1]))->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
