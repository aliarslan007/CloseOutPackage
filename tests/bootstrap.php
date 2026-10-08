<?php

declare(strict_types=1);

/**
 * Test bootstrap — pins the test environment BEFORE Laravel boots.
 *
 * The dev shell/IDE exports the project's real `.env` into the process
 * environment (`DB_CONNECTION=mysql`, `DB_DATABASE=pm-db`). Two framework
 * behaviours then let those values leak into the suite:
 *
 *   - PHPUnit's `<env>` only sets a variable that is not already present
 *     (see PhpHandler::handleEnvVariables) — without `force="true"` an
 *     exported value silently wins.
 *   - Laravel's Dotenv repository is immutable, so `.env.testing` cannot
 *     override an already-set variable either.
 *
 * Result: `RefreshDatabase` ran `migrate:fresh` against the real database.
 *
 * Writing to every adapter Dotenv reads — `$_SERVER`, `$_ENV` and `putenv()`
 * — makes that impossible no matter how the shell is configured. Keep this
 * list in sync with the `<php><env>` block in phpunit.xml.
 */
$testEnvironment = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($testEnvironment as $name => $value) {
    $_SERVER[$name] = $value;
    $_ENV[$name] = $value;
    putenv("{$name}={$value}");
}

require __DIR__.'/../vendor/autoload.php';
