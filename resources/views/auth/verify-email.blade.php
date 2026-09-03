<x-guest-layout title="Verify your email">
    <h1 class="text-lg font-semibold text-gray-900">Check your email</h1>
    <p class="mt-2 text-sm text-gray-600">
        We've sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it and you're in.
        Didn't get it? Check your spam folder, or send another below.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 callout-success">
            A new verification link has been sent to your email address.
        </div>
    @endif

    @if ($mailUi ?? null)
        <div class="mt-4 callout-info">
            <strong>This is a test instance.</strong> Email isn't delivered to real inboxes here — read it at
            <a href="{{ $mailUi }}" target="_blank" rel="noopener" class="link">{{ $mailUi }}</a>,
            find the message addressed to you, and click its verification link.
        </div>
    @elseif ($demoLink ?? null)
        <div class="mt-4 callout-info">
            <strong>This is a test instance</strong> without an email service, so here is your verification link directly:
            <a id="verify-email-link" href="{{ $demoLink }}" class="link block mt-1 break-all">Verify my email address</a>
        </div>
    @endif

    <div class="mt-6 flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>{{ __('Resend verification email') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link text-sm">{{ __('Log out') }}</button>
        </form>
    </div>
</x-guest-layout>
