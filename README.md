# KSM

Marketplace e directory per aziende e prodotti. Riscrittura su base pulita,
stesso stack del progetto precedente: Laravel 12 e PHP 8.2.

## Avvio in locale

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Il database predefinito e SQLite, il file sta in `database/database.sqlite`.
Il seeder crea contenuti di prova e l'utenza `admin@example.test` con password
`password`. Sono dati finti: vanno rimossi prima di qualsiasi uso reale.

I piani non ci sono: vanno inseriti da **Piani** in amministrazione. Finche'
manca un piano la directory resta vuota, perche' un'azienda si vede solo se
ne ha uno attivo. Per accendere le aziende di prova, da **Abbonamenti** si
assegna loro un piano.

## In produzione

Nel `.env` del server, diverso da quello di sviluppo:

    APP_ENV=production
    APP_DEBUG=false
    LOG_LEVEL=warning

Con `APP_DEBUG=true` un errore mostra a chiunque percorsi, query e parte del
codice. Dopo ogni modifica del `.env` va rilanciato `artisan optimize`.

`vendor/` non e' nel repository e sul server Composer non gira: si prepara
in locale, in una cartella a parte con solo `composer.json` e
`composer.lock`, senza i pacchetti di sviluppo e con la mappa delle classi
gia' fatta, e si carica a mano:

```bash
composer install --no-dev --optimize-autoloader --no-scripts
```

Non `--classmap-authoritative`: congelerebbe l'elenco delle classi di
`app/` al giorno della preparazione, e la prima classe nuova arrivata con
un deploy non si troverebbe piu'. Appena caricato `vendor/` si fa subito
"Deploy HEAD Commit": il suo `optimize:clear` butta l'elenco dei pacchetti
in cache, che nomina ancora quelli di sviluppo tolti.

Serve un solo cron, ogni minuto (impostato su ilnetwork il 25/09/2026):

    * * * * * /usr/local/bin/ea-php83 /home2/ilnetwork/ksm-next/artisan schedule:run >> /dev/null 2>&1

Da li' partono rinnovi, controllo dei domini, KMoney, pulizia della cache e
la coda (`routes/console.php`). I giri girano dentro il processo di
`schedule:run`, perche' sull'hosting `proc_open()` e' disabilitato.

Sul server i CSS e i JS tengono la cache del browser per un anno (i
`.htaccess` di `public/css`, `public/js` e `public/vendor`): i link portano
`?v=` con la data del file, e il deploy copia i file tenendo le date, cosi'
cambia solo il link di quelli modificati.

## Dati del sito originale

Al posto dei dati di prova si possono caricare aziende e prodotti veri dal
dump phpMyAdmin del vecchio database, messo in `database/ksmdb_databaseoriginale.sql`
(non va in git: contiene password cifrate e chiavi dei gestori di pagamento).

```bash
php artisan migrate:fresh
php artisan legacy:import
```

Non serve MySQL: il comando legge il file riga per riga e scrive nel database
configurato, in circa un minuto. Alla fine importa anche domini della rete,
pagine CMS e campagne banner; su un database gia' importato si lanciano da
soli con `php artisan legacy:import-content`, se quelle tabelle sono vuote. Tiene gli id originali, quindi parte solo su
tabelle vuote, e prende piani, categorie, marchi, utenti, aziende, prodotti e
varianti. Ordini, sessioni e impostazioni restano fuori.

Cosa cambia passando al nuovo schema:

| Dato | Come arriva |
|---|---|
| Piani | stessi id e prezzi; `priority` rovesciata (prima 1 era il piu' ricco), `features` tradotte in capacita'; Ecommerce, Vetrina e Biglietto senza scadenza |
| Abbonamenti | uno attivo per ogni azienda con piano, dalla data di creazione; senza scadenza per i piani che non scadono |
| Regione | ricavata dalla localita' "Regione,Provincia,Citta'"; vuota se non riconosciuta |
| Citta' | se mancava, l'ultima parte della localita' |
| Amministratori | ruolo `super-admin`, stessa password di prima |
| Immagini | i percorsi restano `uploads/...`: i file vanno copiati a parte |

## Struttura

### Disponibilità e varianti dei prodotti

La giacenza vuota (`NULL`) indica stock non gestito: il prodotto è disponibile e
il pagamento non decrementa la giacenza. Zero indica esaurito; un numero positivo
limita le quantità acquistabili. Nei prodotti **Con varianti** queste regole si
applicano alla singola variante; il prezzo vuoto eredita il prezzo del prodotto.
Ogni variante scelta rimane distinta nel carrello e nell'ordine.

Per correggere un database importato prima di questa modifica, dopo le migrazioni:

```bash
php artisan legacy:restore-stock
php artisan legacy:restore-stock --apply
```

Il comando legge il dump originale, ripristina gli stock `NULL` convertiti in zero
e normalizza il vecchio tipo `variant` in `variable`. Verifica ID, slug, azienda e
data di modifica; non sovrascrive i prodotti modificati dopo l'importazione.


| Cartella | Contenuto |
|---|---|
| `app/Models` | 21 modelli, uno per entita |
| `app/Http/Controllers` | sito pubblico, area azienda, amministrazione |
| `app/Support` | carrello, contesto dominio, formattazione importi, navigazione |
| `app/Services` | integrazione KMoney |
| `routes/` | `web.php`, `admin.php`, `vendor-area.php`, `account.php`, `pages.php` |
| `resources/views` | viste Blade, divise per area |
| `public/css` | foglio di stile, senza framework |

### Ordine delle rotte

`routes/pages.php` contiene la rotta jolly delle pagine CMS e viene caricata
per ultima, dopo amministrazione, area azienda e area cliente. Se si aggiunge
una sezione con un prefisso nuovo, va aggiunta anche all'elenco `$reserved` in
quel file.

## Chi puo' fare cosa

Tre tipi di utenza, nella colonna `users.user_type`: `buyer` chi acquista,
`vendor` chi ha un'azienda, `admin` chi entra in amministrazione.

Dentro l'amministrazione il tipo non basta: ogni persona ha un **ruolo**, e il
ruolo e' un elenco di permessi. L'elenco valido sta in
`app/Support/Permissions.php` ed e' chiuso, come le voci dei piani: un permesso
tolto da li' sparisce ovunque.

| Pezzo | Dove sta | Cosa fa |
|---|---|---|
| Elenco dei permessi | `app/Support/Permissions.php` | raggruppati per area, con l'etichetta mostrata |
| Ruolo | `roles`, colonna JSON `permissions` | nome, descrizione, permessi spuntati |
| Traduzione in regole | `app/Providers/AuthServiceProvider.php` | definisce un Gate per ogni permesso |
| Porta d'ingresso | `EnsureUserIsAdmin` | tipo giusto, accesso non sospeso, un ruolo assegnato |
| Controllo per pagina | `routes/admin.php` | ogni sezione dichiara il suo `can:` |

La regola e' costante: l'elenco chiede il permesso "vedere", tutto il resto
chiede "gestire". Le voci del menu compaiono con lo stesso permesso della
pagina a cui portano, cosi' nessuno vede una voce che poi risponde 403.

Il ruolo `super-admin` e' di sistema: concede tutto, non si puo' eliminare e i
suoi permessi non si possono togliere. Nasce nella migrazione dei ruoli, non
nel seeder, perche' la migrazione successiva lo assegna agli amministratori
gia' presenti. Chi non ce l'ha non lo puo' nemmeno assegnare ad altri:
altrimenti bastano i permessi sugli utenti per farsi amministratori da soli.

Due ruoli di esempio arrivano con la migrazione, **Redazione** e **Assistenza
clienti**, e si possono cambiare o eliminare.

Collaboratori si aggiungono da **Utenti**: tipo `Amministrazione`, un ruolo, e
una password comunicata a voce. Un accesso si sospende senza eliminarlo, e
nessuno puo' sospendere o declassare se stesso.

## Area cliente

`/account`, per chiunque abbia fatto accesso: riepilogo, i propri ordini con lo
stato di avanzamento, dati e indirizzo abituale.

L'indirizzo sta sull'utente e serve solo a precompilare la cassa. Gli ordini
gia' fatti conservano l'indirizzo di quel giorno: `Order` non legge mai da
`User`. Il filtro sugli ordini sta nella query, non in un controllo dopo, cosi'
non c'e' un ramo in cui l'ordine di un altro viene caricato.

## Aspetto

I colori stanno in `public/css/tokens.css`: il blu `#0B4662` della testata per
le fasce scure e il verde `#51A52C` per le azioni. Cambiare i valori li
propaga a tutto il sito, pannelli compresi. I colori della testata per singolo
dominio stanno in `config/header.php`.

L'immagine dell'apertura della home va in `public/img/hero.jpg`. Senza il
file, al suo posto compare una trama neutra.

Testi di intestazione, apertura e sezioni della home stanno in
`lang/it/site.php` e `lang/en/site.php`, non nelle viste.

## Piede e dati del gestore

Ragione sociale, partita IVA, indirizzo, telefono, email e sito compaiono nel
piede e si modificano da **Amministrazione, Impostazioni**, insieme agli
indirizzi delle pagine Facebook e Instagram. L'icona di una rete compare solo
se il suo indirizzo e' compilato.

I valori di Gruppo Kosmos stanno in `database/seeders/SiteSettingsSeeder.php`.
`legacy:import` riparte da tabelle vuote e lascia fuori le impostazioni: dopo
un'importazione vanno ripristinati.

```bash
php artisan db:seed --class=SiteSettingsSeeder
```

Il seeder compila solo i campi vuoti e non tocca quelli modificati a mano.

## Ricerca

La home ha una sola casella e il filtro per regione. La casella cerca nel nome
dell'azienda, nella citta', nella regione, nel settore e nei nomi dei prodotti
in vendita. Le 20 regioni stanno in `config/ksm.php` e si assegnano sulla
scheda azienda: all'attivazione, dal profilo o dall'amministrazione.

## Motori di ricerca ed errori

`/sitemap.xml` e' un indice: rimanda a `/sitemap-pagine-1.xml` e a file da
5.000 indirizzi per aziende e prodotti. Il protocollo ne ammette 50.000 per
file, ma file sotto il megabyte sono piu' leggeri da servire. Ogni file resta in cache sei ore. `/robots.txt` e'
generato per il dominio corrente ed esclude le aree riservate; per questo in
`public/` non c'e' un robots statico, che lo coprirebbe.

Le pagine di errore stanno in `resources/views/errors` e non usano il layout
del sito: un 500 puo' nascere proprio dal database che il layout interroga.

## Mappa e descrizioni

### Mappa OpenStreetMap

La scheda pubblica di un'azienda mostra la mappa quando l'azienda ha le
coordinate e il piano comprende la scheda contatti. Leaflet 1.9.4 sta in
`public/vendor/leaflet`, senza CDN; lo script delle mappe e' `public/js/maps.js`.

Le coordinate (`companies.latitude`, `longitude`) si decidono nel modulo,
in amministrazione e nel profilo azienda:

- chi sposta o mette il segnaposto sulla mappa decide, e non si cerca niente;
- altrimenti, se l'indirizzo e' cambiato o le coordinate mancano, al
  salvataggio si cerca con Nominatim (`App\Support\Maps\CompanyLocation`):
  prima la *posizione sulla mappa* scritta apposta, poi indirizzo, citta' e
  regione. Se Nominatim non risponde la scheda si salva lo stesso, senza mappa.

La politica di Nominatim vieta le ricerche in blocco: le aziende importate
prendono le coordinate quando qualcuno ne salva la scheda, non tutte insieme.
Le tessere pubbliche di openstreetmap.org vanno bene per un traffico
contenuto; con molte visite si passa a un fornitore cambiando
`KSM_MAP_TILES`. Le tessere arrivano da un server esterno, che vede
l'indirizzo IP del visitatore: va detto nell'informativa privacy, anche se
OpenStreetMap non usa cookie.

### Editor delle descrizioni

Descrizione dell'azienda (amministrazione e profilo) e descrizione del
prodotto hanno un editor con grassetto, corsivo, sottolineato, elenchi e
collegamenti (`public/js/richtext.js`, nessuna libreria). Chi incolla da Word
o da una pagina web porta solo la formattazione ammessa; senza script resta
la casella di testo.

`App\Support\RichText` ripulisce l'HTML due volte: al salvataggio, cosi' nel
database finisce solo formattazione, e alla visualizzazione, per le
descrizioni importate dal sito originale. Il testo semplice resta testo e va
a capo dove l'azienda e' andata a capo. Le pagine CMS non usano questo
editor: possono contenere immagini, che il filtro toglierebbe.

## Domini e certificati

I domini della rete (**Domini**) e i domini propri delle aziende (scheda
azienda, **Dominio proprio**) sono collegati quando due cose sono vere:
il DNS punta al server e il certificato risponde valido. Lo verifica
`App\Support\Domains\DomainConnectionChecker`, e lo stato compare negli
elenchi: *Da verificare*, *DNS da configurare*, *Certificato in attesa*,
*Collegato*. Cambiare il dominio azzera la verifica.

`php artisan domains:check` rifa' tutte le verifiche ed e' programmato
ogni ora; dall'amministrazione c'e' anche **Verifica ora**.

Il cliente punta il dominio al server con un record A verso l'indirizzo in
`KSM_SERVER_IPS` (piu' indirizzi separati da virgola), oppure con un CNAME
verso `KSM_SERVER_CNAME`.

### Chi emette il certificato

La verifica e' la stessa ovunque; cambia chi emette il certificato.

**VPS (oggi, Hostinger).** Caddy con *on demand TLS*: il certificato
nasce alla prima visita del dominio. Prima di chiederlo, Caddy domanda a
`/tls/autorizza?domain=...` se il dominio e' della piattaforma. La rotta
risponde 200 o 404, e solo agli indirizzi in `KSM_TLS_ASK_IPS` (di
default la macchina stessa): senza questo filtro chiunque puntasse un
dominio al server farebbe emettere certificati a nostro nome.

```
{
    on_demand_tls {
        ask http://127.0.0.1:8000/tls/autorizza
    }
}

https:// {
    tls {
        on_demand
    }
    reverse_proxy 127.0.0.1:8000
}
```

Dietro Caddy l'app riceve richieste in http: `https`, dominio e IP veri
arrivano negli `X-Forwarded-*`, accettati solo dagli indirizzi in
`KSM_TRUSTED_PROXIES` (di default la macchina stessa, vedi
`App\Http\Middleware\TrustPlatformProxies`). Da altri indirizzi gli header
si ignorano. `www.dominio` rimanda con un 301 a `dominio`
(`RedirectWww`), tranne le aziende con `force_www`.

### DNS del cliente

- A `@` e A `www` verso l'IP del server (oppure CNAME `www` verso il dominio);
- nessun AAAA che punti altrove: Let's Encrypt proverebbe l'IPv6;
- con Cloudflare record "solo DNS", non in proxy;
- un eventuale CAA deve ammettere `letsencrypt.org`; gli MX non si toccano.

**cPanel.** Il certificato lo emette AutoSSL, ma solo per domini che
cPanel conosce, e Apache serve solo quelli. Con `KSM_CPANEL_URL`,
`KSM_CPANEL_USER` e `KSM_CPANEL_TOKEN` (cPanel -> Sicurezza -> Gestisci
token API) ogni dominio della rete e ogni dominio proprio di un'azienda
viene aggiunto da solo come dominio aggiuntivo con la cartella
`KSM_CPANEL_DOCROOT` (predefinita `ksm-next/public`): al salvataggio, a
"Verifica ora" e nel giro orario `domains:check`, che chiede anche il
certificato ad AutoSSL quando il DNS e' gia' giusto. Un rifiuto di cPanel
finisce nell'errore del dominio. Non si toglie mai niente dal pannello.
Se il dominio e' gia' una zona di un altro account del cluster DNS
dnshigh, cPanel lo rifiuta finche' quella zona non sparisce. Il giro
orario verifica soltanto: nel pannello un dominio entra solo al
salvataggio o con "Verifica ora".

Il pacchetto di hnksmsho non ammette domini aggiuntivi, quindi i domini
diventano alias di ksmshop.it e arrivano in `public_html`, che e' il sito
WordPress. In cima al suo `.htaccess` il blocco `# BEGIN ksm-next`
manda ogni host diverso da ksmshop.it e pizzerie.it (e dai sottodomini
di servizio di cPanel, e da `/.well-known/` per AutoSSL) a
`/ksmnext/`, un collegamento a `ksm-next/public` creato da cron con
`ln -sfn`. Il blocco sta fuori dai marcatori di WordPress, che quindi
non lo riscrive; la copia di prima e' `.htaccess.bak-ksmnext`.

## Posta

Sul cPanel condiviso `proc_open()` e' disabilitato, quindi il trasporto
`sendmail` di Symfony non parte: fallisce con *Call to undefined function
... proc_open()* e nessuna mail esce, verifiche degli indirizzi comprese.
Si esce con SMTP sulla porta locale, che usa un socket e non lancia
processi:

    MAIL_MAILER=smtp
    MAIL_HOST=moff.dnshigh.com
    MAIL_PORT=25
    MAIL_USERNAME=null
    MAIL_PASSWORD=null
    MAIL_SCHEME=null

L'host e' il nome della macchina, non `localhost`: Exim annuncia STARTTLS
e Symfony lo attiva da solo, ma il certificato e' intestato a
`moff.dnshigh.com` e con `localhost` la verifica fallisce. Dal server
stesso non servono credenziali. Il `.env` e' in cache: dopo ogni modifica
va rilanciato `artisan optimize`, altrimenti non cambia niente.

## Pagamenti

Ogni azienda incassa sul proprio conto: le credenziali stanno in
`company_payment_settings` e si inseriscono da **Area azienda, Incassi**.

Lo strato sta in `app/Payments`. `GatewayManager` decide cosa si puo'
proporre in cassa: serve che l'azienda abbia attivato il metodo, che le
credenziali ci siano, e che qui esista un driver. Se manca uno dei tre, il
metodo non compare.

Il percorso e sempre lo stesso:

1. L'ordine e il pagamento nascono in stato `pending`.
2. Il driver apre la sessione presso il gestore e restituisce l'indirizzo a
   cui mandare l'acquirente. Il carrello si svuota solo a questo punto: se il
   gestore rifiuta, la spesa resta dov'e.
3. Al rientro su `/pagamento/rientro/{ordine}` l'esito viene **richiesto al
   gestore**, mai letto dai parametri dell'indirizzo.
4. Non basta che il gestore dica "pagato": l'incasso deve essere dell'importo
   dovuto e nella stessa valuta. Se non corrisponde, l'ordine resta in attesa
   e lo scarto finisce nella risposta salvata sul pagamento.
5. A incasso confermato l'ordine passa a `paid` e la giacenza viene scalata,
   una volta sola anche se la pagina viene ricaricata.

Driver disponibili:

| Metodo | Come funziona |
|---|---|
| Stripe | Checkout ospitato, libreria `stripe/stripe-php`. I dati della carta non passano da qui. |
| PayPal | API Orders v2 via client HTTP. Il vecchio SDK REST e dismesso e non viene usato. |
| KMoney | API v1, pagamento ospitato da KMoney con il token del venditore. Solo per la quota KMoney: vedi sotto. |

Per provare il percorso dal browser serve inserire le proprie chiavi di test
dalla pagina Incassi dell'azienda. Il seeder non ne mette nessuna: una chiave
finta farebbe comparire il metodo in cassa per poi fallire all'apertura.

### Come sono verificati

I driver veri sono esercitati dai test, con le chiamate ai gestori
intercettate. Non servono account: si prova il nostro codice, non il loro
servizio.

| File | Cosa prova |
|---|---|
| `StripeSignatureTest` | La firma delle notifiche, calcolata con lo schema di Stripe: valida, con segreto sbagliato, inventata, scaduta. |
| `StripeConfirmTest` | La conferma: importo o valuta diversi non fanno passare l'ordine. |
| `PayPalGatewayTest` | Apertura ordine, collegamento di approvazione, cattura, cattura gia avvenuta, verifica della firma. |
| `CheckoutReturnTest` | Il rientro dell'acquirente e l'idempotenza. |
| `PaymentWebhookTest` | La rotta delle notifiche, firma respinta compresa. |

Resta fuori dalla prova solo il dialogo con i server veri di Stripe e PayPal,
che richiede credenziali.

### Notifiche dei gestori

Se l'acquirente chiude il browser dopo aver pagato, il rientro non avviene
mai. Per questo i gestori richiamano il sito per conto proprio:

```
POST /webhook/stripe/{azienda}
POST /webhook/paypal/{azienda}
```

Gli indirizzi sono in chiaro nella pagina Incassi di ogni azienda, pronti da
incollare nel pannello del gestore. Contengono l'azienda perche' ogni azienda
incassa con le proprie credenziali e ha un proprio dato di verifica:
`stripe_webhook_secret` per Stripe, `paypal_webhook_id` per PayPal.

Le rotte stanno in `routes/webhooks.php` e **non** passano dal gruppo `web`:
niente sessione e niente token CSRF, perche' a chiamare e un server. A
proteggerle e la firma della notifica, che il driver verifica prima di
toccare qualsiasi cosa. Firma non valida significa `400` e nessuna modifica.

Eventi ascoltati:

| Gestore | Evento | Effetto |
|---|---|---|
| Stripe | `checkout.session.completed`, `checkout.session.async_payment_succeeded` | rilegge la sessione e registra l'incasso |
| PayPal | `CHECKOUT.ORDER.APPROVED` | esegue la cattura e registra l'incasso |
| PayPal | `PAYMENT.CAPTURE.COMPLETED` | registra l'incasso se non e gia registrato |

Notifica e rientro finiscono nella stessa funzione di chiusura, che e
idempotente: arrivino in qualsiasi ordine, o tutte e due, la giacenza viene
scalata una volta sola.

La firma si verifica subito, dentro la richiesta del gestore. La chiusura del
pagamento va in coda con `ProcessPaymentNotification`: il gestore riceve
risposta in pochi millisecondi, e una verifica non riuscita viene ripetuta
fino a cinque volte. La coda la svuota ogni minuto lo scheduler
(`routes/console.php`), perche' sull'hosting condiviso non c'e' un processo
sempre acceso: basta il cron di `php artisan schedule:run`. Su un server con
supervisor si puo' tenere acceso un lavoratore al suo posto.

```bash
php artisan queue:work
```

## KMoney

Ogni prodotto si paga in parte in KMoney (KY), secondo una quota di 0, 25,
50, 75 o 100. 1 KY vale 1 euro. Il cliente paga prima la quota KMoney sul
sito KMoney, poi subito il resto in euro al venditore con Stripe o PayPal.
L'ordine diventa pagato, e la giacenza scende, solo quando sono pagate
tutte e due le parti.

### Chi decide la quota

Dal piu' forte al piu' debole (`App\Payments\KMoney\KMoneyShare`):

1. conto KMoney del venditore **in debito**: 100, e il venditore non puo'
   cambiare niente;
2. la quota scelta sul **prodotto**, da solo o selezionato con altri
   dall'elenco prodotti (area azienda e amministrazione);
3. la quota scelta per la **categoria** (area azienda, **KMoney**, e scheda
   azienda in amministrazione);
4. la quota del **contratto** KMoney del venditore.

La quota vale anche se il venditore non ha ancora collegato il conto
KMoney: non si trasforma in euro da sola. In quel caso la cassa non accetta
l'ordine, a meno che il cliente scelga di pagare tutto in euro dove
l'amministrazione lo consente. La spedizione segue la quota piu' bassa del
carrello. Gli importi si dividono in
centesimi interi: la parte KMoney si arrotonda per difetto, il resto va in
euro.

`products.kmoney_percent` e' la quota effettiva gia' calcolata, per schede e
filtro del catalogo; si ricalcola quando cambiano prodotto, categorie,
contratto o debito. La cassa non la usa: rifa' il conto.

Contratto e debito dovrebbero arrivare dall'API KMoney, ma il plugin
WooCommerce usa solo i pagamenti e la guida dell'API non c'e'. Finche'
manca, li imposta l'amministrazione dalla scheda azienda.

### Chi non ha un conto KMoney

Se in **Impostazioni, Pagamenti** e' spuntato *Chi non ha un conto KMoney
puo' pagare tutto in euro*, in cassa compare la scelta. Non vale per i
venditori in debito: da loro si paga solo in KMoney.

### Se la parte in euro non va a buon fine

L'ordine resta in attesa con la quota KMoney pagata, e la pagina di
pagamento non completato propone di riprovare, anche con un altro metodo.
Prima di ripartire si chiede al gestore se il tentativo precedente in
realta' e' andato, per non far pagare due volte. I KY non si restituiscono
da qui: il rimborso lo fa il venditore dal suo conto KMoney.

### Configurazione

- `KMONEY_API_BASE_URL`: indirizzo dell'API v1, per esempio
  `https://kmoney.example/api/v1`.
- Il venditore, da **Incassi**: token API (`km_...`, permesso di scrittura)
  e secret del webhook. Token e secret sono cifrati nel database.
- Sul portale KMoney: webhook verso `/webhook/kmoney/{azienda}`, evento
  `payment_request.paid`. La firma `X-KMoney-Signature` e' un HMAC SHA-256
  del corpo.

Il vecchio `KMoneyService`, che chiedeva email e password KMoney del
cliente, non c'e' piu'.

## Circuito banner

Le campagne banner le crea e le fattura l'amministrazione, da **Campagne
banner** e **Inserzionisti**. Gli inserzionisti sono aziende del sito, che
vedono le campagne nell'area azienda sotto **Pubblicita'**, oppure esterni,
che si registrano da `/inserzionisti/registrati` e hanno la loro area in
`/area-inserzionista`. Nessuno dei due crea o modifica campagne: vede
visualizzazioni, clic, CTR e scadenze.

### Dove compare una campagna

- **Posizioni**, con le chiavi del sito originale (`App\Support\Ads\Placements`):
  home sotto la ricerca, sotto le aziende in evidenza, sopra il piede,
  popup; elenco aziende; catalogo; scheda prodotto. Nelle viste le dichiara
  `<x-ad-slot>`, il popup `<x-ad-popup>`.
- **Domini**, **citta'** e **categorie di aziende**: un bersaglio vuoto vale
  ovunque. Il sito principale nei domini e' `0`. La citta' e la categoria
  vengono dalla pagina (filtri dell'elenco aziende, azienda del prodotto) o
  dal dominio della rete; una categoria madre comprende le sottocategorie.
- Sui siti propri delle aziende non compare nessun banner.

Se piu' campagne vanno bene per la stessa posizione, se ne pesca una a caso
a ogni pagina.

### Come si paga e quando si ferma

A periodo (serve la fine), a visualizzazioni o a clic (servono quelle
acquistate). Una campagna e' in corso se accesa, dentro le date, sotto i
limiti e con l'inserzionista attivo: raggiunto un limite smette di comparire
da sola.

### Cosa si conta

- **Visualizzazione**: quando meta' del banner resta sullo schermo per un
  secondo intero, in una scheda in primo piano, la pagina lo segnala a
  `/banner/{campagna}/vista` (`public/js/ads.js`). L'indirizzo e' firmato
  dal sito e ogni firma conta una volta sola. La stessa persona (indirizzo
  e browser) conta una volta ogni mezz'ora per campagna.
- **Clic**: `/banner/{campagna}/click`, uno per persona all'ora. Su una
  campagna ferma il clic porta al sito ma non si conta.

### Programmi automatici

Chi compra a visualizzazioni o a clic paga solo quelli di persone:

| Chi | Come si ferma |
|---|---|
| Programmi che si dichiarano: motori di ricerca, anteprime dei link nelle app, controlli, script | User-Agent riconosciuto da `App\Support\Ads\BotDetector`; senza User-Agent vale come programma |
| Browser pilotati da un programma | `navigator.webdriver`: ads.js non conta e non apre il popup |
| Pagine preparate dal browser e mai guardate, schede in secondo piano | ads.js aspetta che la pagina sia davvero visibile |
| Chi ricarica o torna sulla pagina | una visualizzazione ogni mezz'ora, un clic ogni ora |

Quello che il server scarta come automatico non tocca totali e limiti, ma
resta nelle statistiche del giorno (`filtered_impressions`,
`filtered_clicks`), visibile anche all'inserzionista.
- Totali sulla campagna, per i limiti; righe per giorno in
  `advertisement_stats`, per le statistiche.

Il popup si apre dopo 4 secondi, al massimo una volta al giorno per
visitatore, e si chiude con la X o cliccando fuori.

## Piani e abbonamenti

Un'azienda e visibile solo se ha un piano pagato. Il piano dice anche cosa
puo' fare: comparire in directory, mostrare logo e banner, avere la vetrina
completa, vendere nello shop.

### Cosa concede un piano

L'elenco delle voci sta in `app/Support/PlanCapabilities.php` ed e chiuso:
una voce tolta da li sparisce ovunque. In amministrazione, sotto **Piani**,
ogni voce e una spunta. Le voci spuntate finiscono in `plans.capabilities`;
`plans.features` resta il testo mostrato in vetrina, che non decide niente.

Chi deve sapere se un'azienda puo' fare qualcosa chiede
`$company->allows(PlanCapabilities::SHOP)`, mai il nome del piano.

I piani non li crea il seeder: si inseriscono da **Piani** con le quote
vere. Finche' non ce n'e' almeno uno, la directory resta vuota e le aziende
registrate restano spente.

### Il percorso di attivazione

1. Registrazione come azienda. Il piano si puo' scegliere subito da
   `/piani` o dal modulo, oppure rimandare: si va avanti lo stesso.
2. Verifica dell'indirizzo, poi `/attivazione`: i dati dell'attivita'.
   L'azienda nasce spenta e senza piano.
3. `/abbonamento`: la scelta del piano apre un periodo `pending`. Chi
   vuole rimandare esce di qui e torna quando vuole.
4. Pagamento con carta, PayPal o bonifico.
5. A incasso confermato il periodo diventa `active`, `companies.plan_id`
   viene allineato e l'azienda si accende.

L'amministratore puo' saltare tutto: da **Abbonamenti** sceglie azienda e
piano e lo attiva subito. Non viene registrato nessun incasso, perche' li'
non entra denaro e segnarne uno falserebbe i conti.

La quota la incassa la piattaforma, non l'azienda: le credenziali sono
quelle di `admin_payment_settings`, i movimenti finiscono in
`admin_transactions`. Lo strato sta in `app/Payments/Subscriptions` e segue
la stessa regola degli ordini: l'esito si chiede al gestore.

Il bonifico e l'eccezione, perche' nessun gestore lo puo' confermare. Mostra
IBAN e causale, e resta in attesa finche' l'amministratore non conferma
l'incasso da **Abbonamenti**.

### Piani senza scadenza

Ecommerce, Vetrina e Biglietto non scadono: in **Piani** hanno la durata
vuota. Anagrafica dura 365 giorni e si rinnova. Un abbonamento a un piano
senza scadenza ha `ends_at` vuoto, e il giro dei rinnovi non lo guarda.

Nei cambi di piano:

| Caso | Cosa si paga | Scadenza |
|---|---|---|
| Da annuale a senza scadenza | quota intera meno il residuo dell'annuale | nessuna |
| Da senza scadenza a un altro piano | quota nuova meno l'intera quota pagata | quella del piano nuovo |
| Rinnovo di un piano senza scadenza | niente | nessuna |

### Scheda azienda in amministrazione

Ha le voci della scheda del sito originale: email e password di accesso
del titolare, piano, categoria, contatti, costi di spedizione, indirizzo,
descrizione, orari per giorno, galleria fino a 10 foto, logo e banner.

Il piano scelto dalla scheda non si scrive solo sull'azienda: apre un
abbonamento attivo senza incasso, come **Abbonamenti**. Togliere il piano
chiude l'abbonamento in corso. Gli orari restano nel formato del sito
originale, `{"Monday": {"start": "09:00", "end": "18:00"}}`; un giorno
senza orari e' chiuso.

### Rinnovo e scadenza

`php artisan subscriptions:renewals` fa il giro quotidiano ed e gia
programmato in `routes/console.php` alle 7 del mattino. Perche' parta, sul
server deve girare `php artisan schedule:run` ogni minuto da cron.

| Quando | Cosa fa |
|---|---|
| 30 giorni alla scadenza | manda il promemoria di rinnovo |
| 15 giorni | lo manda di nuovo |
| 1 giorno | ultimo avviso |
| alla scadenza | spegne l'azienda e lo comunica |

Ogni tappa parte una volta sola, perche' resta segnata in
`reminders_sent`: il comando si puo' lanciare due volte nello stesso
giorno senza rimandare niente. Se la posta non parte, il giro prosegue e
lo scarto finisce nel log.

Spenta vuol dire fuori dalla directory e shop fermo. Non viene cancellato
niente: scheda, prodotti e ordini restano, e un rinnovo pagato riaccende
tutto com'era.

### Promemoria spenti e rinnovo in blocco

Ogni abbonamento ha `send_reminders`. Spento, il giro dei rinnovi non
manda ne' i promemoria ne' l'avviso di scadenza, ma alla scadenza spegne
l'azienda come sempre. Le aziende importate dal database originale li
hanno spenti: le ha inserite Gruppo Kosmos, non le hanno pagate. Si
accendono e spengono da **Abbonamenti**, e un rinnovo conserva la scelta.

Il **Rinnovo in blocco** in **Abbonamenti** allunga di un periodo tutti gli
abbonamenti attivi di un piano che scade, eventualmente solo quelli con
scadenza entro una data. Non apre periodi nuovi e non registra incassi:
sposta avanti la scadenza con un solo UPDATE, cosi' centomila righe si
aggiornano in un attimo. Chi l'ha lanciato resta nel log.

Si rinnovano solo abbonamenti ancora attivi: un'azienda gia' scaduta si
riattiva una per una da **Attiva un piano**. Il blocco va quindi lanciato
prima del 13 novembre 2026, quando scadono le Anagrafiche importate.

### Cambio di piano a meta' periodo

Il conto lo fa `SubscriptionPricing`, e sono tre casi soltanto:

| Caso | Cosa si paga | Scadenza |
|---|---|---|
| Primo piano, o piano scaduto | quota intera | da oggi, per la durata del piano |
| Rinnovo dello stesso piano | quota intera | attaccata in coda a quella attuale |
| Cambio di piano | la differenza sui giorni che restano | invariata |

Nel cambio, il residuo gia' pagato vale come sconto: si paga la quota del
piano nuovo rapportata ai giorni rimasti, meno quella del piano vecchio
sugli stessi giorni. Se il piano nuovo costa meno, non si paga nulla e non
si rimborsa nulla, e il cambio e' immediato.

### Ordine della directory

`/aziende` mostra prima le fasce, dal piano piu ricco al piu economico, e
dentro ogni fascia mescola. Comanda `plans.priority`, che l'amministratore
imposta a mano; a parita' decide il prezzo.

Il mescolamento non lo fa il database, che su SQLite non sa ripetere un
ordine casuale: `App\Support\CompanyDirectory` ordina per una chiave
derivata da un seme. Il seme nasce a ogni visita e viaggia con la
paginazione, cosi' a ogni ricarica l'ordine dentro la fascia cambia ma la
pagina due resta coerente con la uno.

## Cosa c'e

- Pagamento con Stripe e PayPal, con verifica dell'esito al rientro e
  notifiche firmate dai gestori.
- Directory aziende con una sola casella di ricerca, per nome, settore,
  luogo e prodotti, e filtro per regione.
- Catalogo prodotti con filtri, scheda prodotto, recensioni.
- Carrello in sessione, vincolato a una sola azienda per ordine.
- Ordini, stati, tracciamento pubblico con riferimento ed email.
- Piani a pagamento: scelta in registrazione o rimandabile, quota con
  carta, PayPal o bonifico, voci del piano spuntabili in amministrazione,
  attivazione diretta da amministrazione.
- Rinnovo con promemoria a 30, 15 e 1 giorno, spegnimento a scadenza,
  cambio di piano a meta periodo con calcolo della differenza.
- Directory ordinata per piano, casuale dentro ogni fascia.
- Area azienda: profilo, prodotti, ordini, impostazioni di incasso, piano.
- Amministrazione: aziende, categorie, prodotti, marche, ordini, pagamenti,
  piani, pagine CMS, banner, domini, utenti, impostazioni, posta in uscita.
- Ruoli e permessi per lo staff di amministrazione, con menu e rotte filtrati.
- Area cliente: ordini con lo stato di avanzamento, dati, indirizzo abituale
  che precompila la cassa.
- Domini: dominio azienda, dominio categoria, citta, categoria piu citta.
- Due lingue, italiano e inglese.
- Piede con i dati del gestore e i collegamenti social, modificabili.
- Mappa del sito divisa in file, robots per dominio, pagine di errore.
- Varianti prodotto gestibili dall'area azienda.

## Cosa manca

Sono parti dichiarate ma non collegate. Vanno affrontate prima della messa
online.

1. **Velocita' della directory con i dati veri.** Con circa 98.000 aziende
   la home e `/aziende` rispondono in 5-6 secondi. Il tempo va quasi tutto in
   `CompanyDirectory`, che carica tutte le aziende per mescolarle in PHP; la
   condizione sul piano pesa poco. Il rimedio e' calcolare l'ordine nel
   database, con un'espressione derivata dal seme, e leggere solo la pagina
   richiesta.
2. **KMoney.** Il driver segue l'API del plugin WooCommerce 2.0 ma non e'
   stato provato contro il servizio reale. Mancano la guida dell'API e le
   chiamate per leggere in automatico quota del contratto e debito del
   venditore: per ora li imposta l'amministrazione.
3. **Rimborsi e annullamenti.** Non previsti: si gestiscono dai pannelli di
   Stripe e PayPal.
4. **Addebito ricorrente.** Il rinnovo e assistito ma non automatico:
   arrivano i promemoria, poi l'azienda ripassa da `/abbonamento` e paga.
   Non ci sono mandati di addebito su carta.
5. **Scelta della variante in cassa.** Le varianti si gestiscono dall'area
   azienda ma non si scelgono nel carrello. Per questo non compaiono nella
   scheda pubblica: mostrerebbero prezzi che la cassa non applica.
6. **Mappa nelle schede azienda.** Non ripresa.
7. **Importazioni da WordPress e immagini.** Non riprese.
8. **Prova con account veri.** I driver sono esercitati con le chiamate
   intercettate. Il dialogo con i server veri di Stripe e PayPal va provato
   una volta inserite le chiavi di test.
9. **Copertura dei test.** Non hanno test dedicati carrello, recensioni e
   moduli di contatto.
10. **Privacy e cookie.** Non esistono pagine con i testi: vanno scritti da
    chi gestisce il sito e pubblicati da **Pagine**, con posizione piede.
11. **Collegamenti social.** Gli indirizzi di Facebook e Instagram vanno
    inseriti da **Impostazioni**: finche' mancano, le icone non compaiono.

## Note

Le chiavi dei gateway e le password SMTP si inseriscono dai moduli di
amministrazione e restano nel database (la password SMTP cifrata con la
chiave dell'app). La posta in uscita scritta in **Impostazioni** vale solo
se e' accesa la sua casella; spenta, valgono i `MAIL_*` del `.env`. I campi lasciati vuoti non
sovrascrivono i valori gia salvati, e i valori esistenti non vengono
ristampati nelle pagine.
