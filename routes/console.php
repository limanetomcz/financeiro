<?php

use App\Jobs\DespacharAbrirParcelasTodosClientesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DespacharAbrirParcelasTodosClientesJob)
    ->dailyAt('01:15')
    ->name('abrir-parcelas-exigiveis-todos-clientes')
    ->withoutOverlapping();
