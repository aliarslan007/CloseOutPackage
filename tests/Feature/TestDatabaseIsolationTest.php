<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Regression guard for the incident where running the suite wiped the real
 * database: the dev shell exports the project `.env` (DB_DATABASE=pm-db) and
 * both PHPUnit's non-forced `<env>` and Laravel's immutable Dotenv repository
 * defer to it, so `RefreshDatabase` ran `migrate:fresh` against live data.
 *
 * Deliberately NO RefreshDatabase — this test must never migrate or mutate
 * anything. It only asserts where the suite is actually pointed.
 */
it('runs against the isolated in-memory sqlite database', function (): void {
    $connection = DB::connection();

    expect($connection->getDriverName())->toBe('sqlite')
        ->and($connection->getDatabaseName())->toBe(':memory:');
});
