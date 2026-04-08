<?php

use App\Service\SlotService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Прогрев кэша доступности слотов каждые 5 минут с 10:00 до 22:00
        $schedule->call(function () {
            app(SlotService::class)->getAvailability();
        })->everyFiveMinutes()->between('10:00', '22:00')->name('cache:warm-slots');
    })
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
