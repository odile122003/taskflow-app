<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// withoutOverlapping() : si l'envoi d'un lundi prend plus d'une minute (le
// scheduler tourne chaque minute), on ne veut jamais deux exécutions en
// parallèle qui enverraient le rapport en double.
Schedule::command('taskflow:send-weekly-report')
    ->mondays()
    ->at('08:00')
    ->withoutOverlapping();
