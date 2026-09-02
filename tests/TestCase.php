<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Test settings phpunit.xml forces. PHPUnit puts them in $_ENV/putenv,
     * but Laravel's env() consults $_SERVER first — and a Docker container
     * or CI runner exports DB_CONNECTION=mysql there. Mirror them into
     * $_SERVER right before the app boots so the test values always win.
     */
    private const FORCED_ENV = [
        'APP_ENV', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS', 'BROADCAST_CONNECTION',
        'CACHE_STORE', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'MAIL_MAILER',
        'QUEUE_CONNECTION', 'QUEUE_VIA_SCHEDULER', 'SESSION_DRIVER', 'LOG_CHANNEL',
    ];

    public function createApplication()
    {
        foreach (self::FORCED_ENV as $key) {
            if (array_key_exists($key, $_ENV)) {
                $_SERVER[$key] = $_ENV[$key];
            }
        }

        return parent::createApplication();
    }

    /**
     * Belt and braces: refuse to run against anything but in-memory sqlite.
     * This runs before RefreshDatabase gets to migrate:fresh, so a
     * misconfigured environment can never wipe a real database.
     */
    protected function setUpTraits()
    {
        $connection = $this->app['config']->get('database.default');
        $database = $this->app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against '{$connection}' ({$database}); tests require in-memory sqlite. Check phpunit.xml and the environment."
            );
        }

        return parent::setUpTraits();
    }
}
