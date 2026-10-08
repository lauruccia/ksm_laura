<?php

namespace App\Support\Analytics;

/**
 * Nomi italiani di regioni e citta': gli archivi geografici li danno in inglese
 * ("Lombardy", "Milan"), nelle statistiche si leggono come si dicono.
 * Cio' che non e' nell'elenco resta com'e'.
 */
final class Places
{
    private const REGIONS = [
        'lombardy' => 'Lombardia', 'piedmont' => 'Piemonte', 'aostavalley' => "Valle d'Aosta", 'vallee daoste' => "Valle d'Aosta",
        'tuscany' => 'Toscana', 'apulia' => 'Puglia', 'puglia' => 'Puglia', 'sicily' => 'Sicilia', 'sardinia' => 'Sardegna',
        'trentinosouthtyrol' => 'Trentino-Alto Adige', 'trentinoaltoadige' => 'Trentino-Alto Adige',
        'friuliveneziagiulia' => 'Friuli-Venezia Giulia', 'emiliaromagna' => 'Emilia-Romagna',
        'lazio' => 'Lazio', 'latium' => 'Lazio', 'veneto' => 'Veneto', 'liguria' => 'Liguria', 'campania' => 'Campania',
        'marche' => 'Marche', 'marches' => 'Marche', 'abruzzo' => 'Abruzzo', 'abruzzi' => 'Abruzzo', 'calabria' => 'Calabria',
        'umbria' => 'Umbria', 'basilicata' => 'Basilicata', 'molise' => 'Molise',
    ];

    private const CITIES = [
        'milan' => 'Milano', 'rome' => 'Roma', 'turin' => 'Torino', 'naples' => 'Napoli', 'florence' => 'Firenze',
        'venice' => 'Venezia', 'genoa' => 'Genova', 'padua' => 'Padova', 'mantua' => 'Mantova', 'syracuse' => 'Siracusa',
        'bolzano' => 'Bolzano', 'bozen' => 'Bolzano', 'trento' => 'Trento', 'trent' => 'Trento', 'cagliari' => 'Cagliari',
        'bologna' => 'Bologna', 'palermo' => 'Palermo', 'bari' => 'Bari', 'catania' => 'Catania', 'verona' => 'Verona',
        'messina' => 'Messina', 'parma' => 'Parma', 'modena' => 'Modena', 'perugia' => 'Perugia', 'pescara' => 'Pescara',
        'ancona' => 'Ancona', 'trieste' => 'Trieste', 'brescia' => 'Brescia', 'bergamo' => 'Bergamo', 'reggiocalabria' => 'Reggio Calabria',
        'reggioemilia' => "Reggio Emilia", 'leghorn' => 'Livorno', 'livorno' => 'Livorno', 'pisa' => 'Pisa',
        'aquileia' => 'Aquileia', 'lecce' => 'Lecce', 'taranto' => 'Taranto', 'salerno' => 'Salerno',
    ];

    public static function region(?string $name): ?string
    {
        return self::pick($name, self::REGIONS, 60);
    }

    public static function city(?string $name): ?string
    {
        return self::pick($name, self::CITIES, 80);
    }

    private static function pick(?string $name, array $map, int $max): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $key = preg_replace('/[^a-z]/', '', strtolower(\Illuminate\Support\Str::ascii($name)));

        return mb_substr($map[$key] ?? $name, 0, $max);
    }
}
