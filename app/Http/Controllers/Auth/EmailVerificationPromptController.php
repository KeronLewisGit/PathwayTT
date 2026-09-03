<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     *
     * On a test instance (APP_DEMO_DATA=true) the page also tells testers
     * where captured email can be read, or — when mail only goes to a log
     * file — shows the verification link itself so nobody gets stuck.
     * Neither appears when demo data is off (production).
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $demo = (bool) config('app.demo_data');
        $mailUi = $demo ? config('app.demo_mail_ui') : null;
        $mailIsCaptured = in_array(config('mail.default'), ['log', 'array'], true);

        return view('auth.verify-email', [
            'mailUi' => $mailUi ?: null,
            'demoLink' => $demo && ! $mailUi && $mailIsCaptured ? (new VerifyEmail)->toMail($user)->actionUrl : null,
        ]);
    }
}
