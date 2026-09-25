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
 * Sull'hosting condiviso proc_open() e' disabilitato, e Schedule::command()
 * lancia ogni comando come processo a parte proprio con proc_open: non
 * partirebbe niente. I giri girano quindi dentro il processo di
 * schedule:run, uno dopo l'altro, nell'ordine scritto qui sotto.
 */
$inProcess = fn (string $command, array $options = []) => Schedule::call(fn () => Artisan::call($command, $options))
    ->name($command);

/*
 * Il giro dei rinnovi: promemoria a 30, 15 e 1 giorno dalla scadenza,
 * poi spegnimento. Una volta al giorno basta; lanciarlo due volte non
 * manda niente due volte.
 */
$inProcess('subscriptions:renewals')->dailyAt('07:00');

/*
 * Il giro dei domini: DNS e certificato di ogni dominio collegato. Il
 * cliente cambia il DNS quando vuole, e ogni ora lo stato si rimette in pari.
 */
$inProcess('domains:check')->hourly()->withoutOverlapping();

/*
 * Lo stato KMoney dei venditori: debito e capacita' di vendita. La
 * notifica di KMoney arriva subito, il giro orario copre quelle perse.
 */
$inProcess('kmoney:sync')->hourly()->withoutOverlapping();

/*
 * I collegamenti KMoney in attesa: appena KMoney approva, token e
 * segreto si ritirano. Il pulsante "Controlla ora" fa lo stesso subito.
 */
$inProcess('kmoney:pairings')->everyFiveMinutes()->withoutOverlapping();

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

/*
 * La coda: notifiche di pagamento e stato KMoney arrivano dai webhook e si
 * elaborano qui. Non c'e' un processo sempre acceso, quindi ogni minuto si
 * svuota la coda e ci si ferma prima del giro dopo. Sta per ultima: puo'
 * durare fino a 50 secondi e gli altri giri non devono aspettarla.
 */
$inProcess('queue:work', ['--stop-when-empty' => true, '--max-time' => 50])->everyMinute()->withoutOverlapping(5);
