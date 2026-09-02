<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use App\Models\User;
use Database\Seeders\Concerns\DemoOnly;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoJobSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Put the demo accounts back to their scripted starting state between
 * tester sessions. Touches ONLY the demo/tester accounts and demo
 * listings; real users and sourced jobs are untouched.
 */
class DemoResetCommand extends Command
{
    use DemoOnly;

    protected $signature = 'demo:reset {--keep-testers : Leave the tester accounts and their data alone}';

    protected $description = 'Reset the demo accounts (and demo listings) to their scripted state';

    public function handle(): int
    {
        if (! self::demoAllowed()) {
            $this->error('Demo data is disabled here (not the local environment and APP_DEMO_DATA is not true).');

            return self::FAILURE;
        }

        $emails = $this->option('keep-testers')
            ? array_filter(DemoUserSeeder::DEMO_EMAILS, fn ($e) => ! str_starts_with($e, 'tester'))
            : DemoUserSeeder::DEMO_EMAILS;

        foreach (User::query()->whereIn('email', $emails)->get() as $user) {
            // Resume files live outside the DB; remove them before the cascade.
            foreach ($user->resumes as $resume) {
                Storage::disk(config('resume.disk'))->delete($resume->path);
            }
            $user->delete(); // cascades: profile, skills, matches, applications, plans, achievements, resumes
            $this->line("Reset {$user->email}");
        }

        // Demo listings are re-created (and re-linked to skills) by the seeder.
        JobListing::query()->where('source', 'demo')->delete();

        $this->call('db:seed', ['--class' => DemoUserSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoJobSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoActivitySeeder::class, '--force' => true]);

        $this->info('Demo accounts and listings are back to their starting state.');

        return self::SUCCESS;
    }
}
