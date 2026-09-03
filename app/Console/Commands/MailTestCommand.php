<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Send a plain test email with the configured mailer and say exactly what
 * happened — the fastest way to check SMTP settings on a new instance.
 *
 *   php artisan mail:test you@example.com
 */
class MailTestCommand extends Command
{
    protected $signature = 'mail:test {to : Address to send the test message to}';

    protected $description = 'Send a test email using the configured mailer and report the outcome';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $cfg = config("mail.mailers.{$mailer}", []);

        $this->line("Mailer:   <info>{$mailer}</info>");
        if ($mailer === 'smtp') {
            $this->line(sprintf('SMTP:     %s:%s  scheme=%s  user=%s',
                $cfg['host'] ?? '?', $cfg['port'] ?? '?', $cfg['scheme'] ?: '(auto)', ($cfg['username'] ?? '') !== '' ? $cfg['username'] : '(none)'));
        }
        $this->line(sprintf('From:     %s <%s>', config('mail.from.name'), config('mail.from.address')));
        $this->line("To:       {$this->argument('to')}");

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER is \"{$mailer}\": nothing will reach a real inbox. Set MAIL_MAILER=smtp and the MAIL_* values in .env.");
        }

        try {
            Mail::raw(
                "This is a test message from PathwayTT sent at ".now()->timezone(config('app.display_timezone'))->format('d M Y H:i')." AST.\n\nIf you can read this, email delivery is configured correctly.",
                fn ($message) => $message->to($this->argument('to'))->subject('PathwayTT test email'),
            );
        } catch (Throwable $e) {
            $this->error('Send FAILED: '.$e->getMessage());
            $this->line('Common causes: wrong port/scheme pair (587 → smtp, 465 → smtps), an ordinary password where an app password is required, or the provider blocking the sender address.');

            return self::FAILURE;
        }

        $this->info($mailer === 'log'
            ? 'Message written to storage/logs (not delivered).'
            : 'Message accepted by the mail server. Check the inbox (and spam folder).');

        return self::SUCCESS;
    }
}
