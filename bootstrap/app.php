<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Prioritet: fiskalizacija → parking → email
        $schedule->command('reservations:process-pending')->everyFiveMinutes();
        $schedule->command('payment:check-pending-inquiry')->everyFiveMinutes();
        $schedule->command('post-fiscalization:retry')->everyTenMinutes();
        $schedule->command('reservations:expire-pending')->everyTenMinutes();
        $schedule->command('reservations:assign-late-success')->everyFifteenMinutes();
        $schedule->command('parking:update-availability')->everyTenMinutes();
        $schedule->command('reservations:send-emails')->everyTenMinutes();
        $schedule->command('temp-data:cleanup')->daily();
    })
    ->create();
