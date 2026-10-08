<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Default the Brevo transactional API to a successful stub so queued email
     * jobs never hit the real network during tests. Individual tests override
     * this with their own Http::fake() when they assert on the payload.
     */
    protected function setUp(): void
    {
        $this->guardAgainstNonIsolatedDatabase();

        parent::setUp();

        config()->set('services.brevo.key', 'test-brevo-key');
        config()->set('services.brevo.url', 'https://api.brevo.com/v3/smtp/email');

        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test'], 201),
        ]);
    }

    /**
     * Hard stop that runs before any migration.
     *
     * The dev shell/IDE exports the project's real `.env` (DB_DATABASE=pm-db)
     * into the process environment, and both PHPUnit's `<env>` (without
     * `force`) and Laravel's immutable Dotenv repository defer to an
     * already-set variable. tests/bootstrap.php overrides those values; this
     * asserts the override actually took effect, so a misconfiguration fails
     * loudly instead of running `migrate:fresh` against real data.
     */
    private function guardAgainstNonIsolatedDatabase(): void
    {
        $connection = $_SERVER['DB_CONNECTION'] ?? $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $database = $_SERVER['DB_DATABASE'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');

        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run tests: DB_CONNECTION=%s, DB_DATABASE=%s. Tests must use the '
            .'isolated in-memory sqlite database — check tests/bootstrap.php and phpunit.xml.',
            var_export($connection, true),
            var_export($database, true),
        ));
    }
}
