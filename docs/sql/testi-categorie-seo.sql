-- Testi per le pagine /categorie/... (tabella category_landing_texts)
-- Prima di lanciarlo la tabella deve esistere (SQL della schermata "Testi pagine categoria").
--
-- Per ogni categoria, due righe:
--   place_key ''  = la pagina della categoria (tutta Italia), con titolo, descrizione, testo sopra e approfondimento;
--   place_key '*' = modello per tutte le città e regioni della categoria (titolo, descrizione, testo sopra),
--                   con i segnaposto {categoria}, {luogo}, {numero}, {sito}.
-- INSERT IGNORE: dove una riga c'è già (testo scritto da te) non viene toccata.
-- Le categorie si trovano per slug, non per id: se una non esiste sul sito, la riga viene saltata.
-- Il titolo della pagina (H1) resta quello automatico.

SET NAMES utf8mb4;

-- Mangiare e Bere
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Mangiare e bere: ristoranti, bar, vini | {sito}', 'Ristoranti, pizzerie, bar, pub, enoteche e produttori di vino in tutta Italia: scopri {numero} aziende con indirizzo e contatti su {sito}.', 'In questa categoria trovi {numero} aziende italiane del mondo del cibo e del vino: ristoranti e pizzerie, bar e pub, enoteche e cantine. Scegli il tipo di locale o di prodotto e poi la città in cui cerchi.', 'Per scegliere dove mangiare o cosa acquistare conviene partire da ciò che cerchi: una cena con prenotazione, un aperitivo, una bottiglia da regalare o un prodotto tipico da far arrivare a casa. Le sottocategorie ti aiutano a stringere subito il campo: ristoranti e pizzerie per mangiare fuori, bar e pub per colazione e serata, vini ed enoteche per bere e regalare.

Prima di andare, controlla indirizzo, telefono e orari di apertura sulla scheda e chiama se vuoi prenotare o chiedere di allergie e intolleranze. Se acquisti vino o prodotti tipici, verifica condizioni di spedizione e tempi di consegna.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'mangiare-e-bere';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Ristoranti, bar, vini e prodotti tipici per {luogo}: {numero} aziende con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Scegli il tipo di locale o di prodotto dalle sottocategorie e confronta orari e contatti prima di prenotare.', NOW(), NOW() FROM company_categories WHERE slug = 'mangiare-e-bere';

-- Salute e Bellezza
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Salute e bellezza: parrucchieri, dentisti, estetica | {sito}', 'Parrucchieri, centri estetici, dentisti e centri benessere in tutta Italia: {numero} aziende con indirizzo, telefono e contatti su {sito}.', 'Qui trovi {numero} attività dedicate alla cura della persona: parrucchieri e barbieri, centri estetici e beauty center, studi dentistici e altri servizi per il benessere.', 'La cura di sé è una scelta di fiducia, quindi vale la pena confrontare più di un indirizzo. Guarda quali servizi offre l''attività, se lavora su appuntamento e in quali giorni è aperta. Per i trattamenti estetici chiedi in anticipo durata, prodotti usati e costo; per un parrucchiere, se ha esperienza sul tipo di taglio o di colore che ti interessa.

Per le professioni sanitarie, come i dentisti, verifica che il professionista sia iscritto al relativo albo e fatti consegnare un preventivo scritto prima di iniziare le cure. Le urgenze si gestiscono meglio chiamando direttamente lo studio.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'salute-e-bellezza';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Parrucchieri, centri estetici e dentisti per {luogo}: {numero} attività con indirizzo e telefono su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Confronta servizi e orari e fissa un appuntamento direttamente con l''attività.', NOW(), NOW() FROM company_categories WHERE slug = 'salute-e-bellezza';

-- Costruire e Abitare
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Costruire e abitare: imprese edili, infissi, materassi | {sito}', 'Imprese edili, serramenti, materassi, pulizie e antiquariato in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}.', 'Questa categoria raccoglie {numero} aziende per costruire, ristrutturare e arredare casa: imprese edili, infissi e serramenti, materassi e reti, imprese di pulizia e antiquariato.', 'Per un lavoro in casa il preventivo è lo strumento principale: chiedine più di uno, per iscritto, con voci di costo, materiali e tempi indicati chiaramente. Un sopralluogo prima di impegnarsi permette all''azienda di dare cifre realistiche e a te di capire come lavora.

Per i cantieri chiedi i documenti di regolarità dell''impresa, come il DURC, e quali garanzie offre sul lavoro svolto e sui materiali. Per forniture come infissi o materassi controlla anche tempi di posa o consegna e condizioni di reso.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'costruire-e-abitare';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Imprese edili, infissi, materassi e pulizie per {luogo}: {numero} aziende con contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi più preventivi scritti e confronta materiali, tempi e garanzie.', NOW(), NOW() FROM company_categories WHERE slug = 'costruire-e-abitare';

-- Servizi
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Servizi per famiglie, condomìni e imprese | {sito}', 'Amministratori di condominio, onoranze funebri, trasporti e logistica e altri servizi: {numero} aziende in tutta Italia, con contatti su {sito}.', 'Nella categoria Servizi trovi {numero} aziende che lavorano per famiglie, condomìni e imprese: amministrazione di condominio, onoranze funebri, trasporti e logistica e molto altro.', 'I servizi si scelgono sulla fiducia e sulla chiarezza delle condizioni. Prima di firmare un incarico o un contratto chiedi per iscritto cosa è compreso nel prezzo, quanto dura l''impegno e come si può recedere. Un''azienda seria risponde volentieri a queste domande.

Per orientarti usa le sottocategorie: ognuna riunisce chi svolge lo stesso mestiere, così puoi confrontare più aziende della tua zona e contattare direttamente quelle che ti convincono di più.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'servizi';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Amministratori, onoranze funebri, trasporti e altri servizi per {luogo}: {numero} aziende su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Per ogni servizio confronta condizioni e costi prima di firmare.', NOW(), NOW() FROM company_categories WHERE slug = 'servizi';

-- Regali e Preziosi
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Regali e preziosi: gioielli, orologi, giocattoli | {sito}', 'Orologi e gioielli, articoli da regalo, giocattoli e bigiotteria: {numero} aziende italiane con indirizzo e contatti su {sito}.', 'Idee regalo e oggetti preziosi in un solo posto: {numero} aziende tra orologerie e gioiellerie, negozi di articoli da regalo, giocattoli e bigiotteria.', 'Un regalo riuscito nasce da due domande: per chi è e quanto vuoi spendere. Da qui scegli la sottocategoria giusta: gioielli e orologi per le occasioni importanti, articoli da regalo per ricorrenze e piccoli pensieri, giocattoli per i più piccoli.

Per i preziosi chiedi sempre garanzia e documentazione dell''oggetto e controlla il marchio di identificazione sui metalli preziosi. Se acquisti a distanza verifica spedizione, imballaggio e condizioni di reso, soprattutto per un regalo con una data precisa.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'regali-e-preziosi';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Gioielli, orologi, giocattoli e regali per {luogo}: {numero} negozi con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Confronta i negozi e chiedi garanzie e condizioni di reso prima di scegliere.', NOW(), NOW() FROM company_categories WHERE slug = 'regali-e-preziosi';

-- Dormire
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Dormire in Italia: alberghi e hotel | {sito}', 'Alberghi e hotel in tutta Italia: scopri {numero} strutture con indirizzo, telefono e contatti diretti su {sito}.', 'Cerchi un posto dove dormire? Qui trovi {numero} strutture ricettive, in particolare alberghi e hotel, con i contatti per scrivere o chiamare direttamente.', 'Per scegliere dove dormire parti dalla posizione: vicino al centro, alla stazione o alla zona che devi raggiungere. Poi guarda i servizi che contano per te, come colazione, parcheggio, accesso per persone con disabilità o animali ammessi, e chiedi direttamente alla struttura se non sono indicati.

Contattare l''albergo senza intermediari permette di chiedere condizioni di cancellazione, orari di arrivo e di partenza e richieste particolari. Conferma sempre per iscritto prenotazione e tariffa.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'dormire';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Alberghi e hotel per {luogo}: {numero} strutture con indirizzo e telefono per prenotare su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Contatta direttamente la struttura per tariffe, servizi e condizioni di cancellazione.', NOW(), NOW() FROM company_categories WHERE slug = 'dormire';

-- Sport
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Sport: negozi, attrezzature e abbigliamento | {sito}', 'Attività e negozi legati allo sport in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}. Scegli per città o regione.', 'Nella categoria Sport trovi {numero} aziende legate all''attività fisica e al tempo libero: articoli e abbigliamento sportivo, attrezzature e servizi per chi pratica sport.', 'Chi pratica sport sa che l''attrezzatura giusta fa la differenza. Prima di acquistare chiarisci il livello a cui giochi o ti alleni, la stagione in cui userai l''articolo e il budget, e chiedi consiglio al negoziante su taglie e materiali.

Per abbigliamento e calzature verifica le condizioni di cambio, per le attrezzature la garanzia e l''assistenza. Se cerchi un servizio, come un corso o un impianto, telefona per conoscere orari e modalità di iscrizione.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'sport';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Articoli e servizi per lo sport per {luogo}: {numero} aziende con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Confronta negozi e servizi e chiedi consiglio prima di acquistare.', NOW(), NOW() FROM company_categories WHERE slug = 'sport';

-- Auto e Moto
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Auto e moto: officine, ricambi e accessori | {sito}', 'Officine, ricambi e accessori per auto e moto in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}.', 'Per la tua auto o la tua moto: {numero} aziende tra autofficine, rivenditori di ricambi e accessori, in tutta Italia.', 'Quando porti il veicolo in officina chiedi un preventivo scritto che distingua manodopera e ricambi e fatti spiegare quali interventi sono urgenti e quali possono aspettare. Chiedi anche se i ricambi sono originali o equivalenti e quale garanzia è prevista sul lavoro.

Per ricambi e accessori controlla la compatibilità con marca, modello e anno del veicolo prima dell''acquisto: la scheda del prodotto o il negoziante possono aiutarti. Conserva sempre fattura e documenti degli interventi.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'auto-e-moto';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Officine, ricambi e accessori per {luogo}: {numero} aziende con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Per officine e ricambi chiedi sempre un preventivo scritto.', NOW(), NOW() FROM company_categories WHERE slug = 'auto-e-moto';

-- Elettronica e Tecnologia
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Elettronica e tecnologia: telefonia e dispositivi | {sito}', 'Telefonia, dispositivi e accessori elettronici in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}.', 'Smartphone, accessori e dispositivi elettronici: {numero} aziende che vendono e assistono tecnologia, in tutta Italia.', 'Prima di comprare un dispositivo definisci l''uso che ne farai e il budget, poi confronta caratteristiche e prezzi. Chiedi se il prodotto è nuovo, ricondizionato o usato e quali accessori sono inclusi nella confezione.

Per i consumatori la garanzia legale di conformità dura 24 mesi: chiedi al venditore come gestisce assistenza e riparazioni e conserva lo scontrino o la fattura. Per gli acquisti a distanza controlla anche tempi di consegna e diritto di recesso.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'elettronica-e-tecnologia';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Telefonia e dispositivi elettronici per {luogo}: {numero} negozi con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi disponibilità, garanzia e assistenza prima dell''acquisto.', NOW(), NOW() FROM company_categories WHERE slug = 'elettronica-e-tecnologia';

-- Ristoranti e pizzerie
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Ristoranti e pizzerie in Italia: elenco e contatti | {sito}', 'Trova ristoranti e pizzerie in tutta Italia: {numero} locali con indirizzo e telefono per prenotare. Scegli per città o regione su {sito}.', 'Ristoranti, trattorie e pizzerie: {numero} locali in tutta Italia, con indirizzo e telefono per prenotare. Scegli la tua città o regione per vedere quelli vicino a te.', 'Per scegliere dove mangiare pensa all''occasione: una cena veloce, un pranzo di lavoro, una serata con gli amici o una ricorrenza con tanti invitati. Per i gruppi e per i fine settimana conviene prenotare, e il telefono riportato nella scheda serve proprio a questo.

Quando chiami puoi anche chiedere se c''è un menu per intolleranze e allergie, se il locale ha posti all''aperto, se accetta animali o è accessibile con passeggini e sedia a rotelle. Controlla gli orari di apertura, perché molti locali chiudono un giorno a settimana.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'ristoranti-e-pizzerie';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Ristoranti e pizzerie per {luogo}: {numero} locali con indirizzo e telefono per prenotare su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiama per prenotare e chiedi di menu per intolleranze, posti all''aperto e orari di apertura.', NOW(), NOW() FROM company_categories WHERE slug = 'ristoranti-e-pizzerie';

-- Bar e Pub
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Bar e pub in Italia: colazioni, aperitivi e serate | {sito}', 'Bar, caffetterie e pub in tutta Italia: {numero} locali con indirizzo e contatti su {sito}. Scegli per città o regione.', 'Dal caffè del mattino all''aperitivo, fino alla serata con birra e musica: {numero} bar e pub in tutta Italia.', 'Il bar giusto cambia a seconda dell''ora: colazione e pausa pranzo chiedono rapidità e qualità del caffè, l''aperitivo uno spazio piacevole, la serata un locale con atmosfera e, a volte, musica dal vivo. Dai un''occhiata ai dettagli della scheda per capire che tipo di locale è.

Se vuoi organizzare una festa o una serata di gruppo, chiama in anticipo per chiedere disponibilità e condizioni. Verifica gli orari, soprattutto nei giorni festivi, e se il locale propone eventi o menu a tema.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'bar-e-pub';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Bar, caffetterie e pub per {luogo}: {numero} locali con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Controlla gli orari e chiama in anticipo se vuoi organizzare una serata di gruppo.', NOW(), NOW() FROM company_categories WHERE slug = 'bar-e-pub';

-- Parrucchieri
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Parrucchieri in Italia: elenco e contatti | {sito}', 'Cerca parrucchieri e saloni in tutta Italia: {numero} attività con indirizzo e telefono per prenotare, su {sito}. Scegli la tua città.', 'Taglio, colore, piega o trattamenti: {numero} parrucchieri e saloni in tutta Italia, con i contatti per prendere appuntamento.', 'Un buon parrucchiere si sceglie guardando prima di tutto cosa offre: tagli donna e uomo, colore, trattamenti per la cura dei capelli, acconciature per cerimonie. Se hai in mente un lavoro preciso, come una colorazione impegnativa, chiedi con anticipo se il salone lo realizza e quanto tempo richiede.

Prenota telefonando, soprattutto il sabato e nei giorni prima delle feste. Per un primo appuntamento puoi chiedere un breve colloquio sul risultato che desideri e sul costo indicativo, così eviti sorprese.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'parrucchieri';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Parrucchieri e saloni per {luogo}: {numero} attività con indirizzo e telefono per prenotare su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Telefona per prenotare e chiedi il costo indicativo del servizio che ti interessa.', NOW(), NOW() FROM company_categories WHERE slug = 'parrucchieri';

-- Imprese Edili
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Imprese edili in Italia: preventivi e contatti | {sito}', 'Imprese edili per costruzioni e ristrutturazioni in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}. Scegli per città.', 'Costruzioni, ristrutturazioni e manutenzioni: {numero} imprese edili in tutta Italia, da contattare direttamente per un preventivo.', 'Una ristrutturazione o una nuova costruzione si affida a chi lavora con metodo. Descrivi bene il lavoro, chiedi un sopralluogo e confronta almeno due o tre preventivi scritti: devono indicare lavorazioni, materiali, costi e tempi, non solo il totale.

Chiedi i documenti di regolarità dell''impresa, come il DURC, e quali garanzie offre sulle opere eseguite. Prima di firmare definisci le modalità di pagamento, che dovrebbero seguire lo stato di avanzamento dei lavori, e come verranno gestite eventuali varianti.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'imprese-edili';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Imprese edili per costruzioni e ristrutturazioni per {luogo}: {numero} aziende con contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Richiedi un sopralluogo e confronta preventivi scritti, garanzie e tempi.', NOW(), NOW() FROM company_categories WHERE slug = 'imprese-edili';

-- Amministratori di Condominio
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Amministratori di condominio in Italia | {sito}', 'Amministratori di condominio in tutta Italia: {numero} professionisti e studi con indirizzo e contatti su {sito}. Scegli per città.', 'Studi e professionisti per la gestione del condominio: {numero} amministratori in tutta Italia da contattare direttamente.', 'L''amministratore gestisce bilanci, spese comuni, manutenzioni e rapporti con i fornitori del condominio. Per sceglierlo chiedi quanti condomìni segue, come comunica con i condòmini e come si organizza per le emergenze.

Il codice civile prevede requisiti per svolgere l''incarico, tra cui titolo di studio e formazione iniziale e periodica: chiedi di poterli verificare. Il compenso va indicato con chiarezza al momento della nomina, voce per voce, in modo che l''assemblea possa decidere con tutte le informazioni.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'amministratori-di-condominio';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Amministratori di condominio per {luogo}: {numero} professionisti e studi con contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi quanti condomìni segue l''amministratore, come comunica e quale compenso propone.', NOW(), NOW() FROM company_categories WHERE slug = 'amministratori-di-condominio';

-- Onoranze Funebri
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Onoranze funebri in Italia: contatti e informazioni | {sito}', 'Agenzie di onoranze funebri in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}. Cerca nella tua città o regione.', 'In un momento delicato è importante sapere a chi rivolgersi: {numero} agenzie di onoranze funebri in tutta Italia, con indirizzo e recapiti.', 'Scegliere un''agenzia di onoranze funebri richiede serenità e chiarezza. Chiedi un preventivo scritto e dettagliato, con le voci di servizio una per una, in modo da sapere con precisione cosa è compreso e cosa no. Un''agenzia seria spiega con pazienza ogni passaggio e rispetta le volontà della famiglia.

Verifica che l''agenzia sia raggiungibile in ogni momento, anche di notte e nei giorni festivi, e quali pratiche amministrative segue per tuo conto. Se possibile, parlane con qualcuno di fiducia prima di decidere.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'onoranze-funebri';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Agenzie di onoranze funebri per {luogo}: {numero} aziende con indirizzo e recapiti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi un preventivo scritto e dettagliato e verifica la disponibilità dell''agenzia in ogni momento.', NOW(), NOW() FROM company_categories WHERE slug = 'onoranze-funebri';

-- Alberghi - Hotel
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Alberghi e hotel in Italia: contatti diretti | {sito}', 'Alberghi e hotel in tutta Italia: {numero} strutture con indirizzo e telefono per prenotare direttamente. Scegli la città su {sito}.', '{numero} alberghi e hotel in tutta Italia, con indirizzo e contatti per prenotare direttamente, senza intermediari.', 'Che tu viaggi per lavoro o per turismo, la scelta dell''albergo parte da posizione, categoria e periodo del soggiorno. Contatta la struttura per chiedere disponibilità, tariffe per le tue date e cosa è compreso: colazione, parcheggio, connessione, navetta.

Chiedi le condizioni di cancellazione e di pagamento prima di confermare e fai indicare per iscritto data di arrivo, tipo di camera e prezzo. Se viaggi in gruppo o con bambini, segnala subito le esigenze particolari.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'alberghi-hotel';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Alberghi e hotel per {luogo}: {numero} strutture con indirizzo e telefono per prenotare su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi disponibilità e tariffe per le tue date e le condizioni di cancellazione.', NOW(), NOW() FROM company_categories WHERE slug = 'alberghi-hotel';

-- Orologi e Gioielli
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Orologi e gioielli in Italia: negozi e contatti | {sito}', 'Orologerie e gioiellerie in tutta Italia: {numero} negozi con indirizzo e contatti su {sito}. Scegli per città o regione.', 'Orologi, gioielli e oggetti preziosi: {numero} orologerie e gioiellerie in tutta Italia.', 'Un orologio o un gioiello è un acquisto che dura nel tempo. Chiedi la scheda dell''oggetto con materiale, caratteristiche e provenienza e la garanzia del prodotto. Per i gioielli in metallo prezioso controlla il marchio di identificazione e chiedi il peso e il titolo del metallo.

Un negozio affidabile offre anche assistenza: sostituzione di batteria e cinturini, riparazioni, pulizia e adattamento della misura. Chiedi quali servizi sono inclusi e quanto costano, e conserva la fattura per eventuali interventi in garanzia.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'orologi-e-gioielli';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Orologerie e gioiellerie per {luogo}: {numero} negozi con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi garanzia, documentazione dell''oggetto e servizi di assistenza prima di acquistare.', NOW(), NOW() FROM company_categories WHERE slug = 'orologi-e-gioielli';

-- Vini
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Vini italiani: cantine, produttori e rivendite | {sito}', 'Cantine, produttori e rivendite di vino in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}. Cerca per regione.', 'Cantine, produttori e rivendite di vino da ogni parte d''Italia: {numero} aziende da contattare per degustazioni, acquisti e spedizioni.', 'L''Italia ha una varietà di vini che cambia da regione a regione, ed è per questo che la ricerca per regione è il modo più naturale per esplorare. Se hai in mente un vino preciso, cerca nella regione d''origine; se vuoi scoprire qualcosa di nuovo, chiedi consiglio al produttore.

Molte cantine accolgono visitatori per degustazioni e vendita diretta: chiama per sapere se serve prenotare. Per ordinare a distanza chiedi costi e tempi di spedizione e come viene imballata la merce. Per un regalo, chiedi se è possibile una confezione dedicata.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'vini';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Cantine, produttori e rivendite di vino per {luogo}: {numero} aziende con contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiama per sapere se è possibile una degustazione o la vendita diretta.', NOW(), NOW() FROM company_categories WHERE slug = 'vini';

-- Imprese di Pulizia
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Imprese di pulizia in Italia: preventivi e contatti | {sito}', 'Imprese di pulizia per casa, uffici e condomìni in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}.', 'Pulizie per casa, ufficio, condominio e attività commerciali: {numero} imprese di pulizia in tutta Italia, da contattare per un preventivo.', 'Prima di chiedere un preventivo definisci il servizio che ti serve: pulizie periodiche o una tantum, ordinarie o straordinarie, in una casa, in un ufficio o nelle parti comuni di un condominio. Più il quadro è preciso, più il preventivo sarà confrontabile con gli altri.

Chiedi quali giorni e orari sono previsti, chi fornisce prodotti e attrezzature, come vengono gestite le sostituzioni in caso di assenza del personale e cosa succede se il lavoro non è soddisfacente. Un contratto scritto con prezzo e durata evita malintesi.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'imprese-di-pulizia';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Imprese di pulizia per casa, uffici e condomìni per {luogo}: {numero} aziende con contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Descrivi il servizio che ti serve e confronta preventivi, orari e condizioni.', NOW(), NOW() FROM company_categories WHERE slug = 'imprese-di-pulizia';

-- Accessori e Ricambi
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Accessori e ricambi auto e moto in Italia | {sito}', 'Rivenditori di accessori e ricambi per auto e moto in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}.', 'Ricambi e accessori per auto e moto: {numero} rivenditori in tutta Italia da contattare per disponibilità e prezzi.', 'Per acquistare il ricambio giusto servono i dati del veicolo: marca, modello, anno di immatricolazione e, quando richiesto, il numero di telaio riportato sul libretto. Con questi dati il rivenditore può dirti cosa è compatibile ed evitare acquisti sbagliati.

Chiedi se il ricambio è originale o di qualità equivalente e che garanzia ha. Per gli accessori, dai portapacchi ai tappetini, verifica che siano adatti al tuo modello e, dove serve, omologati. Se spedisci o ritiri, chiedi tempi e costi e le condizioni di reso.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'accessori-e-ricambi';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Accessori e ricambi per auto e moto per {luogo}: {numero} rivenditori con contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Tieni a portata di mano marca, modello e anno del veicolo per verificare la compatibilità.', NOW(), NOW() FROM company_categories WHERE slug = 'accessori-e-ricambi';

-- Articoli da Regalo
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Articoli da regalo in Italia: idee e negozi | {sito}', 'Negozi di articoli da regalo in tutta Italia: {numero} attività con indirizzo e contatti su {sito}. Trova l''idea giusta vicino a te.', 'Compleanni, nascite, matrimoni e ricorrenze: {numero} negozi di articoli da regalo in tutta Italia per trovare l''idea giusta.', 'Per scegliere un regalo parti dalla persona: gusti, età, interessi e occasione contano più del prezzo. Un negozio specializzato può consigliarti e proporti soluzioni per ogni budget, dalle piccole attenzioni agli oggetti importanti.

Chiedi se è possibile personalizzare l''articolo con nome, data o dedica e quanto tempo richiede, e se il negozio offre la confezione regalo. Se il regalo ha una data precisa, fissa per tempo ritiro o consegna e verifica come cambiare l''articolo, se necessario.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'articoli-da-regalo';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Negozi di articoli da regalo per {luogo}: {numero} attività con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi consiglio al negoziante e informati su personalizzazione, confezione e cambio.', NOW(), NOW() FROM company_categories WHERE slug = 'articoli-da-regalo';

-- Telefonia
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Telefonia in Italia: negozi di smartphone e accessori | {sito}', 'Negozi di telefonia, smartphone e accessori in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}.', 'Smartphone, accessori e assistenza: {numero} negozi di telefonia in tutta Italia, da contattare per disponibilità e riparazioni.', 'Quando scegli uno smartphone considera uso, durata della batteria, memoria disponibile e fotocamera in base alle tue abitudini, non solo il prezzo. Chiedi se il telefono è nuovo, ricondizionato o usato e cosa include la confezione.

Per i consumatori la garanzia legale dura 24 mesi dall''acquisto: chiedi al negozio come gestisce assistenza e riparazioni, anche fuori garanzia, e quali costi aspettarti. Molti negozi di telefonia offrono riparazioni, per esempio la sostituzione di schermo o batteria: chiedi preventivo e tempi prima di lasciare il dispositivo e fai un backup dei dati.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'telefonia';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Negozi di telefonia e smartphone per {luogo}: {numero} aziende con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi disponibilità, garanzia e costi di riparazione prima di acquistare.', NOW(), NOW() FROM company_categories WHERE slug = 'telefonia';

-- Dentisti
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Dentisti in Italia: studi dentistici e contatti | {sito}', 'Dentisti e studi odontoiatrici in tutta Italia: {numero} professionisti con indirizzo e telefono su {sito}. Cerca nella tua città.', 'Visite, igiene, cure e urgenze: {numero} dentisti e studi odontoiatrici in tutta Italia, con indirizzo e telefono per fissare un appuntamento.', 'Per scegliere uno studio dentistico chiedi quali prestazioni offre, se si occupa di bambini, se ha specialisti per interventi come ortodonzia o implantologia e come gestisce le urgenze. Il primo appuntamento serve anche a capire se ti trovi bene con il professionista.

Verifica che il dentista sia iscritto all''albo degli odontoiatri e chiedi sempre un piano di cura con preventivo scritto, con le singole voci e i tempi, prima di iniziare. Informati su modalità di pagamento, eventuali rateizzazioni e garanzie sui lavori.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'dentisti';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Dentisti e studi odontoiatrici per {luogo}: {numero} professionisti con indirizzo e telefono su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi un piano di cura con preventivo scritto e informati su come lo studio gestisce le urgenze.', NOW(), NOW() FROM company_categories WHERE slug = 'dentisti';

-- Giocattoli
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Giocattoli in Italia: negozi per bambini | {sito}', 'Negozi di giocattoli e giochi in tutta Italia: {numero} attività con indirizzo e contatti su {sito}. Scegli la tua città.', 'Giochi, costruzioni, peluche e idee regalo per ogni età: {numero} negozi di giocattoli in tutta Italia.', 'Scegliere un giocattolo significa guardare all''età del bambino, a ciò che lo diverte e alla sicurezza. Controlla l''età consigliata riportata sulla confezione e il marchio CE, che indica la conformità del prodotto alle norme europee, e diffida di articoli senza informazioni chiare.

Un negozio specializzato può consigliarti giochi adatti a ogni fase di crescita, dal gioco di manipolazione ai giochi di società da fare in famiglia. Chiedi quali sono i più indicati per un regalo e come funziona il cambio, nel caso il regalo sia doppio.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'giocattoli';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Negozi di giocattoli e giochi per {luogo}: {numero} attività con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Controlla l''età consigliata e il marchio CE e chiedi consiglio al negoziante.', NOW(), NOW() FROM company_categories WHERE slug = 'giocattoli';

-- Antiquariato
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Antiquariato in Italia: negozi e antiquari | {sito}', 'Antiquari e negozi di antiquariato in tutta Italia: {numero} attività con indirizzo e contatti su {sito}. Cerca per città o regione.', 'Mobili, oggetti d''arte e pezzi da collezione: {numero} antiquari in tutta Italia, da contattare per visitare il negozio o chiedere una valutazione.', 'Il mondo dell''antiquariato richiede fiducia nell''esperto. Quando acquisti un pezzo chiedi epoca, materiali, provenienza, stato di conservazione ed eventuali restauri subiti, e fatti rilasciare una fattura con la descrizione dell''oggetto. Per i pezzi di valore chiedi una scheda o una perizia.

Molti antiquari ritirano e valutano oggetti: se vuoi vendere, chiama per sapere se effettuano valutazioni e come si svolgono. Per mobili ingombranti chiedi se il negozio si occupa di imballaggio e trasporto.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'antiquariato';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Antiquari e negozi di antiquariato per {luogo}: {numero} attività con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Chiedi epoca, provenienza e stato di conservazione dei pezzi e se il negozio effettua valutazioni.', NOW(), NOW() FROM company_categories WHERE slug = 'antiquariato';

-- Autofficine
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Autofficine in Italia: elenco e contatti | {sito}', 'Trova autofficine in tutta Italia: {numero} officine con indirizzo e telefono per prenotare tagliando e riparazioni su {sito}.', 'Tagliandi, riparazioni, gomme e controlli: {numero} autofficine in tutta Italia, con indirizzo e telefono per prenotare.', 'Per scegliere un''autofficina chiedi quali interventi esegue, per quali marche lavora e se offre servizi come il tagliando, il cambio gomme, l''elettrauto o la preparazione per la revisione. Un''officina specializzata sulla tua marca può conoscerne meglio i problemi più comuni.

Prima di autorizzare un lavoro richiedi il preventivo scritto con ricambi e manodopera indicati separatamente e fatti spiegare cosa è urgente e cosa può aspettare. Dopo l''intervento ritira la fattura e, se vuoi, i ricambi sostituiti. Chiedi quale garanzia è prevista sul lavoro.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'autofficine';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Autofficine per {luogo}: {numero} officine con indirizzo e telefono per prenotare tagliando e riparazioni su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Telefona per prenotare e chiedi un preventivo scritto con ricambi e manodopera separati.', NOW(), NOW() FROM company_categories WHERE slug = 'autofficine';

-- Materassi e Reti
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Materassi e reti in Italia: negozi e produttori | {sito}', 'Materassi, reti e sistemi per il riposo in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}. Scegli per città.', 'Materassi, reti e cuscini per dormire meglio: {numero} negozi e produttori in tutta Italia.', 'Il materasso giusto dipende dal tuo peso, dalla posizione in cui dormi e da eventuali esigenze di postura. Meglio provarlo in negozio, sdraiandoti per qualche minuto nella posizione abituale, e farsi consigliare su rigidità e materiali, come schiuma, lattice o molle.

Chiedi la garanzia, la possibilità di cambio o di prova a casa e la composizione dei rivestimenti, se hai allergie. Verifica tempi e costi di consegna e se il negozio ritira il materasso usato. Ricorda di abbinare la rete adatta, perché influisce su comfort e durata del materasso.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'materassi-e-reti';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Materassi, reti e sistemi per il riposo per {luogo}: {numero} aziende con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Prova il materasso in negozio e chiedi garanzia, possibilità di cambio e consegna.', NOW(), NOW() FROM company_categories WHERE slug = 'materassi-e-reti';

-- Infissi e Serramenti
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Infissi e serramenti in Italia: aziende e preventivi | {sito}', 'Infissi, finestre e serramenti in tutta Italia: {numero} aziende con indirizzo e contatti su {sito}. Chiedi un preventivo nella tua città.', 'Finestre, porte e serramenti per casa e uffici: {numero} aziende in tutta Italia, da contattare per un sopralluogo e un preventivo.', 'Gli infissi incidono su comfort, isolamento acustico e consumi di casa. Per orientarti chiedi che materiale propongono (PVC, alluminio, legno), che tipo di vetro e quali prestazioni di isolamento termico e acustico garantiscono, e fatti spiegare le differenze con parole semplici.

Il sopralluogo con le misure è il primo passo per un preventivo corretto: deve indicare modelli, materiali, costo della posa e tempi di consegna. Chiedi se l''azienda si occupa anche dello smaltimento dei vecchi infissi e della documentazione utile per eventuali detrazioni fiscali, che vanno verificate caso per caso con il tuo tecnico o commercialista.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'infissi-e-serramenti';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Infissi, finestre e serramenti per {luogo}: {numero} aziende con indirizzo e contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Richiedi un sopralluogo e confronta materiali, prestazioni di isolamento, posa e tempi.', NOW(), NOW() FROM company_categories WHERE slug = 'infissi-e-serramenti';

-- Trasporti e Logistica
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, body, created_at, updated_at)
SELECT id, '', 'Trasporti e logistica in Italia: aziende e contatti | {sito}', 'Aziende di trasporti, spedizioni e logistica in tutta Italia: {numero} imprese con indirizzo e contatti su {sito}.', 'Trasporti, spedizioni e logistica per privati e imprese: {numero} aziende in tutta Italia, da contattare per un preventivo.', 'Per scegliere un''azienda di trasporti descrivi con precisione cosa devi spostare: tipo di merce, peso e dimensioni, luogo di ritiro e di consegna, date desiderate. Più il quadro è preciso, più il preventivo sarà affidabile e confrontabile.

Chiedi tempi di consegna, modalità di tracciamento della spedizione e cosa prevede l''assicurazione in caso di danni o smarrimento. Verifica come vengono gestite le consegne mancate e se sono previsti costi extra, per esempio per il carico in zone difficili da raggiungere.

Per restringere la ricerca scegli una città o una regione tra i link in fondo alla pagina. Ogni scheda riporta indirizzo e contatti e, dove l''azienda ha una vetrina su {sito}, anche prodotti e recensioni.', NOW(), NOW() FROM company_categories WHERE slug = 'trasporti-e-logistica';
INSERT IGNORE INTO category_landing_texts (company_category_id, place_key, meta_title, meta_description, intro, created_at, updated_at)
SELECT id, '*', '{categoria} – {luogo}: {numero} aziende | {sito}', 'Aziende di trasporti, spedizioni e logistica per {luogo}: {numero} imprese con contatti su {sito}.', 'Per {luogo} trovi {numero} aziende nella categoria «{categoria}», con indirizzo e contatti per chiamare o scrivere subito. Descrivi cosa devi spostare e chiedi tempi, tracciamento e copertura assicurativa.', NOW(), NOW() FROM company_categories WHERE slug = 'trasporti-e-logistica';
