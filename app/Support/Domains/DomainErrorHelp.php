<?php

namespace App\Support\Domains;

use Illuminate\Support\Str;

/**
 * Cosa fare davanti a un errore di collegamento di un dominio.
 *
 * Gli errori li scrivono DomainConnectionChecker e i pannelli (WHM, cPanel):
 * qui si riconoscono dal testo e si traducono in passi concreti, con l'IP
 * del server e il pacchetto giusti gia' scritti dentro.
 */
class DomainErrorHelp
{
    /**
     * @return array{title: string, steps: list<string>, tip: ?string}
     */
    public static function for(?string $error): ?array
    {
        $error = trim((string) $error);

        if ($error === '') {
            return null;
        }

        $e = Str::lower($error);
        $ips = implode(', ', (array) config('ksm.server.ips')) ?: 'l\'IP del server (Server e hosting)';
        $cname = config('ksm.server.cname');
        $plan = config('ksm.whm.proxy_plan') ?: 'quello impostato in Server e hosting';
        $record = $cname
            ? "un record A verso {$ips} (oppure un CNAME verso {$cname})"
            : "un record A verso {$ips}";

        return match (true) {
            str_contains($e, 'nome di dominio non valido') => self::help(
                'Il nome del dominio è scritto male',
                [
                    'Apri Modifica sul dominio.',
                    'Scrivi solo il nome, per esempio esempio.it: senza https://, senza www. davanti se non serve, senza / in fondo e senza spazi.',
                    'Salva: la verifica riparte da sola.',
                ],
                'Le lettere accentate vanno bene, ma controlla che il dominio sia registrato proprio così.',
            ),

            str_contains($e, 'indirizzo del server non configurato') => self::help(
                'Manca l\'indirizzo del server',
                [
                    'Vai in Domini → Server e hosting e scrivi l\'IP del server (o più IP separati da virgola).',
                    'In alternativa, se la pagina usa il .env: imposta KSM_SERVER_IPS nel .env del server e lancia php artisan config:clear.',
                    'Poi seleziona i domini e fai Verifica.',
                ],
                null,
            ),

            str_contains($e, 'nessun record dns') => self::help(
                'Il dominio non ha record DNS',
                [
                    'Controlla che il dominio sia registrato e non scaduto (per esempio su whois.nic.it per i .it).',
                    'Nel pannello del registrar (Aruba, Register, OVH…) controlla i nameserver: devono essere quelli del provider dove gestisci il DNS.',
                    "Nella zona DNS aggiungi {$record}, sia per il dominio (@) sia per www.",
                    'Aspetta che il DNS si aggiorni: di solito da pochi minuti a qualche ora, al massimo 24–48 h.',
                    'Poi premi Verifica.',
                ],
                'Puoi controllare lo stato da dnschecker.org: quando quasi tutti i paesi mostrano l\'IP giusto, la verifica passa.',
            ),

            str_contains($e, 'il dominio punta a') => self::help(
                'Il DNS punta a un altro server',
                [
                    'Entra nel pannello del registrar o del provider che gestisce il DNS del dominio.',
                    "Cambia il record A di @ e di www in {$ips}".($cname ? " (oppure usa un CNAME verso {$cname})" : '').'.',
                    'Togli gli altri record A e i record AAAA (IPv6) che puntano altrove: se ne resta uno vecchio, una parte dei visitatori va ancora lì.',
                    'Aspetta che il DNS si aggiorni (dipende dal TTL: di solito da pochi minuti a qualche ora).',
                    'Poi premi Verifica.',
                ],
                'Se l\'IP mostrato è quello del vecchio hosting, il cliente sta ancora usando quel servizio: avvisalo prima di spostare il dominio, per non fermargli email e sito.',
            ),

            str_contains($e, 'certificato non ancora valido') => self::help(
                'Il DNS è giusto, manca ancora il certificato',
                [
                    'Di solito basta aspettare: il certificato (AutoSSL) arriva da pochi minuti a qualche ora dopo che il DNS è corretto.',
                    'Controlla che anche www punti al server: il certificato copre entrambi e fallisce se uno dei due è sbagliato.',
                    'Se dopo qualche ora è ancora così: in WHM → Gestisci AutoSSL → Gestisci utenti, cerca l\'account del dominio e premi Controlla (o in cPanel → SSL/TLS Status → Run AutoSSL).',
                    'Se il DNS ha record CAA, devono permettere letsencrypt.org o sectigo.com.',
                    'Poi premi Verifica.',
                ],
                'Il sito funziona già, ma i browser mostrano un avviso di sicurezza finché il certificato non è pronto.',
            ),

            str_contains($e, 'non risponde') => self::help(
                'Il pannello dell\'hosting non risponde',
                [
                    'Vai in Domini → Server e hosting e premi Prova la connessione.',
                    'Controlla l\'indirizzo del pannello (per la WHM di solito https://nomeserver:2087) e che il token API sia ancora valido: in WHM → Gestisci token API puoi crearne uno nuovo.',
                    'Se il server è in manutenzione o sovraccarico, aspetta qualche minuto.',
                    'Quando la prova va a buon fine: clicca l\'errore in «Errori più frequenti» → Seleziona → Tutti i risultati → Ricollega.',
                ],
                null,
            ),

            str_contains($e, 'account proxy non creato') && (str_contains($e, 'already exists') || str_contains($e, 'già') || str_contains($e, 'esiste gi')) => self::help(
                'Il dominio c\'è già sul server',
                [
                    'In WHM → Elenca account cerca il dominio: probabilmente è già su un altro account (magari un vecchio sito del cliente).',
                    'Se quell\'account non serve più, chiudilo (o togli il dominio da lì); se serve, decidi con il cliente cosa tenere.',
                    'Poi, dalla lista domini, seleziona il dominio e fai Ricollega.',
                ],
                'Prima di eliminare un account controlla che non contenga email o file del cliente.',
            ),

            str_contains($e, 'account proxy non creato') && str_contains($e, 'package') => self::help(
                'La WHM rifiuta il pacchetto',
                [
                    "In WHM → Pacchetti → Modifica un pacchetto controlla che «{$plan}» esista con il nome esatto (maiuscole e minuscole contano).",
                    'Il pacchetto deve avere a 0 caselle email, database, FTP, mailing list e sottodomini: se chiede risorse che il rivenditore non ha, la WHM lo rifiuta.',
                    'Se lo hai cambiato, controlla lo stesso nome in Domini → Server e hosting (vale più del .env) e premi Prova la connessione.',
                    'Poi: clicca l\'errore in «Errori più frequenti» → Seleziona → Tutti i risultati → Ricollega.',
                ],
                'Se il nome è giusto e l\'errore resta, il limite è di Serverplan: usa un pacchetto che funziona o chiedi a loro di sbloccarlo.',
            ),

            str_contains($e, 'account proxy non creato') => self::help(
                'La WHM non ha creato l\'account del dominio',
                [
                    'Leggi il motivo dopo i due punti: è il messaggio della WHM.',
                    'Controlla in WHM → Elenca account se il dominio c\'è già, e che il rivenditore non abbia finito lo spazio o il numero di account.',
                    'Vai in Domini → Server e hosting e premi Prova la connessione.',
                    'Poi seleziona il dominio e fai Ricollega.',
                ],
                null,
            ),

            str_contains($e, 'account proxy') && str_contains($e, 'non e\' stato scritto') => self::help(
                'Account creato, ma i file del collegamento mancano',
                [
                    'Di solito è un problema momentaneo: seleziona il dominio e fai Ricollega, i file vengono riscritti.',
                    'Se si ripete, controlla in WHM che l\'account non sia sospeso o pieno (con il pacchetto da 1 MB lo spazio è poco: i due file pesano pochi KB).',
                    'Controlla che il token API abbia i permessi per gestire gli account del rivenditore.',
                ],
                null,
            ),

            str_contains($e, 'non esiste tra quelli del rivenditore') => self::help(
                'Il pacchetto impostato non esiste',
                [
                    'In WHM → Pacchetti guarda il nome esatto del pacchetto (per esempio rivenditore_1MB).',
                    'Scrivilo uguale in Domini → Server e hosting (o in KSM_WHM_PROXY_PLAN nel .env, poi php artisan config:clear).',
                    'Premi Prova la connessione, poi Ricollega i domini in errore.',
                ],
                null,
            ),

            str_contains($e, 'non ha parcheggiato') || str_contains($e, 'non ha aggiunto il dominio') => self::help(
                'Il pannello non ha aggiunto il dominio',
                [
                    'Il motivo più comune: il dominio è già su un altro account del server. Cercalo in WHM → Elenca account (o in cPanel → Domini).',
                    'Altra causa: l\'account ha finito i domini aggiuntivi o parcheggiati permessi dal pacchetto.',
                    'Se usi la WHM, imposta un pacchetto proxy in Server e hosting: ogni dominio avrà il suo account e il parcheggio non serve più.',
                    'Poi seleziona il dominio e fai Ricollega.',
                ],
                null,
            ),

            default => self::help(
                'Come provare a risolvere',
                [
                    'Premi Verifica: a volte l\'errore era momentaneo.',
                    'Se resta, vai in Domini → Server e hosting e premi Prova la connessione.',
                    'Poi seleziona il dominio e fai Ricollega.',
                ],
                'Il testo in rosso è il messaggio originale del server: cercalo così com\'è se non è chiaro.',
            ),
        };
    }

    /** @param list<string> $steps */
    private static function help(string $title, array $steps, ?string $tip): array
    {
        return ['title' => $title, 'steps' => $steps, 'tip' => $tip];
    }
}
