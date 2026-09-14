<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Il giro dei rinnovi: promemoria a 30, 15 e 1 giorno dalla scadenza,
 * poi spegnimento. Una volta al giorno basta; lanciarlo due volte non
 * manda niente due volte.
 */
Schedule::command('subscriptions:renewals')->dailyAt('07:00');

/*
 * Il giro dei domini: DNS e certificato di ogni dominio collegato. Il
 * cliente cambia il DNS quando vuole, e ogni ora lo stato si rimette in pari.
 */
Schedule::command('domains:check')->hourly()->withoutOverlapping();
