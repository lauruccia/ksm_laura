<?php

namespace App\Support\Analytics;

use App\Models\Conversion;
use App\Models\PageView;
use App\Support\Sites\HostDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Registra un obiettivo raggiunto (registrazione, carrello, ordine, messaggio).
 *
 * Si lega alla visita in corso: si cerca l'ultima visita di quella persona
 * su questo sito oggi e se ne copia la provenienza, cosi' si sa quali canali
 * portano davvero ordini e non solo curiosi. Se la visita non si trova
 * (cookie assenti, IP cambiato) l'obiettivo si conta lo stesso, senza provenienza.
 *
 * Come le pagine viste, rispetta Do Not Track e non conta amministratori e
 * programmi automatici. Un errore qui non deve mai fermare l'azione vera.
 */
final class Conversions
{
    public const SIGNUP = 'signup';

    public const CART = 'cart';

    public const ORDER = 'order';

    public const CONTACT = 'contact';

    public const LABELS = [
        self::SIGNUP => 'Registrazioni',
        self::CART => 'Prodotti nel carrello',
        self::ORDER => 'Ordini',
        self::CONTACT => 'Messaggi inviati',
    ];

    public static function track(Request $request, string $goal, ?float $value = null, string|int|null $ref = null): void
    {
        try {
            if (! array_key_exists($goal, self::LABELS) || ! Visitors::countable($request)) {
                return;
            }

            $host = HostDirectory::normalise($request->getHost());
            $local = Carbon::now(config('ksm.analytics.timezone'));
            $day = $local->toDateString();
            $visitor = Visitors::hash($request, $day);

            $sessionId = PageView::query()
                ->where('visitor', $visitor)
                ->where('host', $host)
                ->where('day', $day)
                ->orderByDesc('id')
                ->value('session_id');

            $entry = $sessionId
                ? PageView::query()->where('session_id', $sessionId)->where('is_entry', true)->first(['channel', 'source', 'campaign', 'country'])
                : null;

            Conversion::create([
                'host' => $host,
                'session_id' => $sessionId,
                'visitor' => $visitor,
                'goal' => $goal,
                'value' => $value,
                'ref' => $ref !== null ? mb_substr((string) $ref, 0, 40) : null,
                'channel' => $entry?->channel,
                'source' => $entry?->source,
                'campaign' => $entry?->campaign,
                'country' => $entry?->country,
                'day' => $day,
                'hour' => (int) $local->format('G'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
