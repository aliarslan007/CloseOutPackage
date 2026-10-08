<?php

use App\Console\Commands\CleanOrphanedMediaAssetsCommand;
use App\Console\Commands\CleanupTempPdfsCommand;
use App\Console\Commands\ComputeDailyProgressReportCommand;
use App\Console\Commands\ExpireCustomerAccessCommand;
use App\Console\Commands\MarkIdleLocationPings;
use App\Console\Commands\ProcessScheduledCustomerEmailsCommand;
use App\Console\Commands\PruneStaleLocationPings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ProcessScheduledCustomerEmailsCommand::class)->everyMinute();
Schedule::command(CleanupTempPdfsCommand::class)->dailyAt('02:00');
Schedule::command(CleanOrphanedMediaAssetsCommand::class)->dailyAt('02:10');
Schedule::command(ExpireCustomerAccessCommand::class)->dailyAt('03:00');
Schedule::command(ComputeDailyProgressReportCommand::class)->dailyAt('23:00');
Schedule::command(MarkIdleLocationPings::class)->everyFiveMinutes();
Schedule::command(PruneStaleLocationPings::class)->dailyAt('04:00');
