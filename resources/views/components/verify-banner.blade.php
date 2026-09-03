{{-- Persistent reminder for users who haven't verified their email yet. --}}
@auth
    @if (! auth()->user()->hasVerifiedEmail())
        @php
            $mailUi = config('app.demo_data') ? config('app.demo_mail_ui') : null;
        @endphp
        <div class="border-b border-accent-200 bg-accent-50 text-accent-900" role="status">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                <p>
                    <strong>Please verify your email.</strong>
                    We sent a link to <span class="font-medium">{{ auth()->user()->email }}</span>. Verifying protects your account and is needed for password resets.
                    @if (session('status') === 'verification-link-sent')
                        <span class="ml-1 font-medium text-green-700">New link sent.</span>
                    @endif
                    @if ($mailUi)
                        <span class="block text-xs text-accent-800 sm:inline sm:ml-1">Test instance: read it at <a href="{{ $mailUi }}" target="_blank" rel="noopener" class="underline">{{ $mailUi }}</a>.</span>
                    @endif
                </p>
                <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="btn-secondary btn-sm">Resend verification email</button>
                </form>
            </div>
        </div>
    @endif
@endauth
