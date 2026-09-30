# Portal Connector API v1 — KSM

Questa API espone a KMoney e agli altri portali autorizzati una vista stabile dei dati commerciali di una singola azienda KSM. La v1 comprende identita, catalogo, ordini, aggiornamento inventario e registrazione delle spedizioni.

## Principi del contratto

- Base path: `/api/management/v1`.
- Autenticazione: `Authorization: Bearer <token>`.
- Ogni token appartiene a una sola azienda. Il client non invia mai `company_id`.
- Scope disponibili: `identity:read`, `catalog:read`, `orders:read`, `inventory:write`, `fulfillments:write`.
- Gli ID esposti sono UUID stabili (`public_id`); gli ID numerici del database restano interni.
- Gli importi sono interi in centesimi. `12,34 EUR` diventa `1234`.
- Le date sono ISO 8601 UTC.
- Gli elenchi sono ordinati per `updated_at`, poi per ID interno, e usano un cursore opaco.
- Il limite predefinito e 50, il massimo 100.
- Ogni risposta autenticata contiene `X-Correlation-ID`.
- Limite di traffico: 120 richieste al minuto per gruppo di rotte.

## Migrazione e creazione del token

Eseguire prima:

```bash
php artisan migrate --force
```

Generare un token con tutti gli scope di lettura, valido 90 giorni:

```bash
php artisan management:issue-token 42 "KMoney produzione"
```

Il primo argomento puo essere l'ID numerico interno dell'azienda oppure il suo UUID pubblico. Per limitare il token e cambiarne la durata:

```bash
php artisan management:issue-token 42 "Catalogo partner" --scope=catalog:read --days=30
```

Ripetere `--scope` per assegnarne piu di uno. `--days=0` crea un token senza scadenza. Il valore completo viene mostrato una volta sola; nel database resta esclusivamente SHA-256. Conservare il token nel secret manager o nelle variabili d'ambiente del client.

Un token con le due capability di scrittura si genera cosi:

```bash
php artisan management:issue-token 42 "Gestionale produzione" \
  --scope=identity:read --scope=catalog:read --scope=orders:read \
  --scope=inventory:write --scope=fulfillments:write
```

## Endpoint

### `GET /identity`

Richiede `identity:read`. Restituisce azienda, valuta, fuso orario, versione e capability effettivamente abilitate sul token.

```json
{
  "data": {
    "company_id": "6ed49880-7395-4f04-87c6-a48d255fa107",
    "name": "Azienda Demo",
    "currency": "EUR",
    "timezone": "Europe/Rome",
    "connector_version": "1.0.0",
    "capabilities": ["identity:read", "catalog:read", "orders:read"]
  }
}
```

### `GET /products` e `GET /products/{id}`

Richiedono `catalog:read`. Il catalogo include varianti, prezzo effettivo, disponibilita e versione incrementale. `stock_managed=false` distingue un prodotto senza gestione giacenza da uno esaurito (`stock_managed=true`, `available_quantity=0`).

```bash
curl -H "Authorization: Bearer $KSM_TOKEN" \
  "https://example.test/api/management/v1/products?limit=50"
```

Salvare sempre `meta.next_cursor` quando presente. Se `meta.has_more` e `true`, usarlo subito per leggere la pagina successiva; se e `false`, conservarlo come punto di partenza della sincronizzazione incrementale seguente:

```text
GET /api/management/v1/products?limit=50&cursor=<next_cursor>
```

### `GET /orders` e `GET /orders/{id}`

Richiedono `orders:read`. Espongono stato commerciale, stato di incasso, totali, effetto sul magazzino, righe e allocazioni dei pagamenti. La v1 non esporta dati anagrafici del cliente, indirizzi, note, tracking o riferimenti delle transazioni. Questa separazione permette ai gestionali contabili di sincronizzare gli ordini senza ricevere dati personali non necessari.

`payment_status` puo essere `unpaid`, `pending`, `partial` o `paid`. `stock_effect` vale `none` oppure `deducted`.

### `PUT /inventory/{id}`

Richiede `inventory:write`. L'ID puo appartenere a un prodotto semplice oppure a una variante. L'aggiornamento abilita la gestione della giacenza e imposta la disponibilita esatta.

```http
PUT /api/management/v1/inventory/5d240196-e5a2-49d0-9837-4eaf9ce12f70
Authorization: Bearer ksm_...
Idempotency-Key: 25758f3e-b9bd-46bf-b406-ce6abf21403a
Content-Type: application/json

{
  "available_quantity": 18,
  "expected_version": 4,
  "reason": "Riconciliazione gestionale"
}
```

La risposta contiene quantità, tipo della risorsa, nuova versione e data di modifica. Se `expected_version` non coincide con quella corrente, la risposta e `409 version_conflict` e contiene `current_version`.

### `POST /orders/{id}/fulfillments`

Richiede `fulfillments:write`. Salva spedizione e tracking, porta l'ordine nello stato `shipped` e applica una sola volta l'effetto sul magazzino. Un ordine `completed` conserva il proprio stato; un ordine annullato restituisce `409 invalid_transition`.

```http
POST /api/management/v1/orders/7e199ad8-2b82-4b6a-a166-231965d51260/fulfillments
Authorization: Bearer ksm_...
Idempotency-Key: 8dcd64bb-fc3f-4c91-92ca-780167edab91
Content-Type: application/json

{
  "expected_version": 2,
  "carrier": "DHL",
  "tracking_number": "TRACK-123",
  "tracking_url": "https://tracking.example/TRACK-123",
  "shipped_at": "2026-09-30T12:30:00+02:00"
}
```

`shipped_at` viene normalizzato in UTC. La registrazione tramite API non invia direttamente email al cliente.

## Idempotenza e concorrenza

Ogni scrittura richiede un header `Idempotency-Key` contenente un UUID. KSM salva azienda, operazione, risorsa, hash del payload, stato HTTP e risposta.

- stessa chiave, stessa risorsa e stesso payload: viene restituita la risposta salvata con `Idempotency-Replayed: true`;
- stessa chiave con payload o risorsa differente: `409 idempotency_conflict`;
- versione non aggiornata: `409 version_conflict`;
- chiave assente o non UUID: `422 validation_failed`.

Il client deve conservare la stessa chiave quando ritenta una richiesta dopo timeout. Una nuova operazione logica deve avere una nuova chiave.

## Outbox webhook

Ogni creazione o modifica di prodotti e ordini registra un evento locale nella tabella `management_webhook_outbox`. Le scritture API aggiungono anche `inventory.updated` e `fulfillment.updated`. Il corpo contiene soltanto ID pubblici, tipo risorsa, versione e data UTC; non contiene anagrafiche cliente, indirizzi o dati di pagamento.

Eventi prodotti:

- `order.created`;
- `order.updated`;
- `product.updated`;
- `inventory.updated`;
- `fulfillment.updated`.

L'outbox viene scritto nella stessa transazione dell'operazione. La consegna HTTP resta disabilitata finche non viene configurato e autorizzato l'URL pubblico HTTPS del gestionale ricevente. In quel passaggio verranno aggiunti firma HMAC, event ID persistente e retry senza modificare il payload applicativo.

## Errori

Gli errori generati dal connettore hanno forma stabile:

```json
{
  "error": {
    "code": "insufficient_scope",
    "message": "Lo scope catalog:read non e autorizzato.",
    "correlation_id": "70c2c2aa-c645-4796-a955-34df0c1093ed"
  }
}
```

- `401 unauthenticated`: token assente, sconosciuto o scaduto.
- `403 insufficient_scope`: token valido senza lo scope richiesto.
- `404`: risorsa inesistente oppure appartenente a un'altra azienda.
- `422 invalid_cursor`: cursore alterato o non valido.
- `429`: limite di richieste superato.

## Regole per i client Laravel

Il client deve conservare separatamente `base_url`, token e ultimo cursore completato per ogni azienda. Deve aggiornare il cursore solo dopo aver salvato con successo tutta la pagina. L'upsert locale usa l'UUID pubblico come chiave e `version` per ignorare payload piu vecchi.

Un timeout o un errore 5xx si puo ritentare con backoff esponenziale. Un 401 o 403 richiede intervento sulla configurazione. Un 422 richiede di scartare il cursore e avviare una sincronizzazione completa controllata.

## Estensioni previste

- consegna dell'outbox al gestionale autorizzato con firma HMAC, retry e deduplicazione;
- eventuale accesso ai dati cliente con scope esplicito e autorizzazione dedicata;
- ulteriori stati logistici senza cambiare i significati gia pubblicati nella v1.

Un client deve usare `capabilities` come fonte di verita e non presumere che una futura funzione sia disponibile solo perche la versione resta `v1`.
