<?php

namespace App\Support;

/**
 * Nomi a dominio del portafoglio KSM: pulizia, lettura di un elenco incollato
 * e tema di appartenenza.
 *
 * Il nome si conserva senza "www." e in minuscolo: la pagina pubblica lo
 * mostra con "www." davanti, come lo digita chi arriva sul sito.
 */
final class DomainNameList
{
    /**
     * Tema dedotto dal nome, solo come punto di partenza: in amministrazione
     * si cambia a mano. Vale la prima regola che corrisponde, quindi l'ordine conta.
     */
    private const THEMES = [
        'Cucina kosher' => '/kosher|kasher/',
        'Sexy shop' => '/sexy/',
        'Alberghi e hotel' => '/albergh|albergo|hotel|apparthotel/',
        'Ristoranti e cibo' => '/ristorant|pizzer|pizza|bistecc|mangiare|caffe|gelater|pasticcer|macelleri|^pub$|cibo|cibi|restaurant|vini|vino|birr|mozzarell|pane|carne|chefame|ecogastro|barrique|dolce/',
        'Moda e accessori' => '/scarpe|calzatur|shoes|abbigliament|moda|fashion|vestiti|borse|bigiotter|gioielli|watches|cinture|^cint[ae]$|intimo|style|stilist|look|divise|omai|albumfotografici/',
        'Salute e bellezza' => '/capelli|trapianto|parrucchier|estetic|cosmetic|profumer|profumi|scoliosi|diagnos|ortopedic|medic|diabetic|cremazion|siero|centrifengshui|casedicura|armonyclub/',
        'Viaggi e vacanze' => '/viaggi|vacanz|turismo|travel|agriturismo|obbiettivomare|obiettivomare|montagna|neve|vedatravel|shoptour|kappatour|aeroporto|stazionetermini|sposi|consulenteinvacanze|consulentiinvacanze|volifree|buonovacanze|midiverto/',
        'Sport e tempo libero' => '/calcio|nonsolosci|danza|ballo|equitazione|gokart|olimpiadi|sportiv|tempolibero|nightclub|discodinner|drivein|pistegokart/',
        'Auto e moto' => '/auto|moto|cardriver|carrelli|furgon|camper|lotus|evocar|elaborazioni|smallcar|revisioni|assetti|ciclomotori|microauto|miniauto|turbocompr|gomme/',
        'Casa e edilizia' => '/infiss|serrament|impiant|termo|climatizz|isolant|edil|restauro|riqualific|bricolegno|pittori|porteblindate|tende|arredament|furniture|casedepoca|casestoriche|caseinlegno|residenze|materassi|piatti|antiacaro|antifurt|antirapina|cablaggio|quadri|elettric|elettrofornit|illuminazione|lampadari|finestre|materiali|cartaabrasiva|centofinestre|fertirrig|irrigazion|agricoltura|bioagricoltura|amianto/',
        'Cultura, eventi e media' => '/canzoni|songs|poesie|giornale|retegiornale|videoteche|cinema|artedeisogni|galleriedarte|kalendari|messaggi|kapodanno|karnevale|feste|perdonanza|eventi|teatral|ceramic|antichita|incisoria|modellisti|gregorio|hifivideo|stampedigitali|europagine|microbanner|cosmosoft|smartzone|freemms|k3g|kookle|rc1|la3|nonetwork/',
        'Animali' => '/canarini|canicorsi|cani|bulldog|mastiff|zwergpinscher|rettili|toeletta|riservedicaccia/',
        'Città e territori' => '/online$|^citta|regione|^quartier|^via|^viale|^valle|isol|riviera|^castel|roma|abruzz|molise|umbria|toscana|marche|puglia|sicilia|corsosicilia|napoli|ravenna|livorno|visso|spinetoli|tolentino|fiuminata|fiano|sora|verol|lirinia|manzian|anagni|bracciano|cassino|formia|frosinone|latina|ostia|fiumicino|frascati|avezzano|cesena|faenza|forli|macerata|civitanova|sangiust|santacaterina|pestum|goladellarossa|valdichienti|valledelvento|potenzapicena|appenninia|margellina|vomero|decamerino|montesangiovanni|passocorese|ramaccioni|adriatico|monterotondo|turismoadriatico/',
        'Servizi e imprese' => '/consulen|assicur|fallim|certific|impres|manutenz|logistic|trasport|spedizion|corrieri|traslochi|assistenza|appalti|aziende|bonifich|spurgo|plottagg|segnaletica|controlloaccessi|fornitur|ingrosso|macchine|fustell|produz|cosmobusiness|consorzio|^firm$|noleggio|conference|salecongressi|salemeeting|assoc|legali|contracambio|dottori|periti|house-commerce|autoformazione|attivitaestrattiv|agentdigital|itici|^kms$|servizimatrimoniali|allestimenti|rotary|risho|detergenti|easypark/',
        'Shopping online' => '/shopping|shop|saldi|sconti|acquist|commerce|regal|gifts|compriamo|store|prodotti|vetrina|flagship|eurobag|cosmomarket|kosmo|exito|intenditori|verisconti|machesconti|percaso|qualsiasi|sempresaldi|scontisempre|saldisempre|nonsolo|obbiettivo|obiettivo|tutto|oggettivetrina|giocattoli|always|eurouno|gifts|cartoler|cartolibrer|articoli|computer|centrocommerciale|casadelmaiale|ladotta|cerchinlega/',
    ];

    /** Il nome pulito, oppure null se non assomiglia a un dominio. */
    public static function normalize(string $raw): ?string
    {
        $host = strtolower(trim($raw));
        // Note a margine come "(SentireGov)" non fanno parte del nome.
        $host = trim(preg_replace('/\(.*$/', '', $host));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);
        $host = preg_replace('#[/?\#].*$#', '', $host);
        $host = preg_replace('/^www\./', '', $host);
        $host = trim($host, " \t.,;:");

        if ($host === '' || strlen($host) > 190) {
            return null;
        }

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $host) ? $host : null;
    }

    /** L'estensione: "it" per "pub.it". */
    public static function extension(string $host): string
    {
        $dot = strrpos($host, '.');

        return $dot === false ? '' : substr($host, $dot + 1);
    }

    /** La parte prima dell'estensione: "pub" per "pub.it". */
    public static function label(string $host): string
    {
        $dot = strrpos($host, '.');

        return $dot === false ? $host : substr($host, 0, $dot);
    }

    public static function theme(string $host): string
    {
        $label = self::label($host);

        foreach (self::THEMES as $theme => $pattern) {
            if (preg_match($pattern, $label)) {
                return $theme;
            }
        }

        return 'Altro';
    }

    /** @return list<string> */
    public static function themes(): array
    {
        return [...array_keys(self::THEMES), 'Altro'];
    }

    /**
     * Legge un elenco incollato: uno per riga, ma vanno bene anche virgole,
     * punti e virgola o spazi. Le parole senza punto (il testo di
     * presentazione, per esempio) si ignorano; le altre che non sono domini
     * validi finiscono fra gli scarti.
     *
     * @return array{valid: array<string, string>, invalid: list<string>}
     */
    public static function parse(string $text): array
    {
        $valid = [];
        $invalid = [];

        foreach (preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $core = trim(preg_replace('/\(.*$/', '', $token), " \t.,;:");

            if (! str_contains($core, '.')) {
                continue;
            }

            $host = self::normalize($token);

            if ($host === null) {
                $invalid[] = $token;
            } else {
                $valid[$host] = $host;
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }
}
