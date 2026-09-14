<?php

namespace App\Support;

/**
 * Orari di apertura di un'azienda.
 *
 * Il formato e' quello del sito originale, con i giorni in inglese come
 * chiavi: {"Monday": {"start": "09:00", "end": "18:00"}}. Cosi' gli orari
 * importati si leggono senza conversioni. Un giorno che manca e' un
 * giorno di chiusura.
 */
final class WorkingHours
{
    public const DAYS = [
        'Monday' => 'Lunedì',
        'Tuesday' => 'Martedì',
        'Wednesday' => 'Mercoledì',
        'Thursday' => 'Giovedì',
        'Friday' => 'Venerdì',
        'Saturday' => 'Sabato',
        'Sunday' => 'Domenica',
    ];

    /** Regole di validazione: per ogni giorno, o tutti e due gli orari o nessuno. */
    public static function rules(string $field = 'working_hours'): array
    {
        $rules = [$field => ['nullable', 'array']];

        foreach (array_keys(self::DAYS) as $day) {
            $rules["$field.$day.start"] = ['nullable', 'date_format:H:i', "required_with:$field.$day.end"];
            $rules["$field.$day.end"] = [
                'nullable', 'date_format:H:i', "required_with:$field.$day.start", "after:$field.$day.start",
            ];
        }

        return $rules;
    }

    /** Nomi leggibili dei campi, per i messaggi di errore. */
    public static function attributes(string $field = 'working_hours'): array
    {
        $names = [];

        foreach (self::DAYS as $day => $label) {
            $names["$field.$day.start"] = "apertura di $label";
            $names["$field.$day.end"] = "chiusura di $label";
        }

        return $names;
    }

    /** Solo i giorni con entrambi gli orari, nell'ordine della settimana. */
    public static function normalize(?array $input): array
    {
        $hours = [];

        foreach (array_keys(self::DAYS) as $day) {
            $start = $input[$day]['start'] ?? null;
            $end = $input[$day]['end'] ?? null;

            if ($start && $end) {
                $hours[$day] = ['start' => $start, 'end' => $end];
            }
        }

        return $hours;
    }
}
