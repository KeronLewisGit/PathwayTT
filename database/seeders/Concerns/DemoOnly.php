<?php

namespace Database\Seeders\Concerns;

/**
 * Demo data is clearly labelled and only ever created where it is wanted:
 * the local environment, or any environment that sets APP_DEMO_DATA=true
 * (e.g. a tester preview instance). Never on a real production site.
 */
trait DemoOnly
{
    public static function demoAllowed(): bool
    {
        return app()->environment('local') || (bool) config('app.demo_data');
    }
}
