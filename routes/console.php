<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

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

/*
 * Lo stato KMoney dei venditori: debito e capacita' di vendita. La
 * notifica di KMoney arriva subito, il giro orario copre quelle perse.
 */
Schedule::command('kmoney:sync')->hourly()->withoutOverlapping();

/*
 * I collegamenti KMoney in attesa: appena KMoney approva, token e
 * segreto si ritirano. Il pulsante "Controlla ora" fa lo stesso subito.
 */
Schedule::command('kmoney:pairings')->everyFiveMinutes()->withoutOverlapping();

/*
 * La coda: notifiche di pagamento e stato KMoney arrivano dai webhook e si
 * elaborano qui. Sull'hosting condiviso non c'e' un processo sempre acceso,
 * quindi ogni minuto si svuota la coda e ci si ferma prima del giro dopo.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(5);

/*
 * Con la cache sul database le chiavi scadute restano nella tabella finche'
 * qualcuno non rilegge la stessa chiave, e quelle dei banner (una per ogni
 * visualizzazione) non si rileggono mai: una volta al giorno si puliscono.
 */
Schedule::call(function () {
    foreach (['cache', 'cache_locks'] as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->where('expiration', '<=', time())->delete();
        }
    }
})->name('cache:prune-expired')->dailyAt('04:30');
