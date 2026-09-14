<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Support\Ads\AdServer;
use App\Support\Ads\BotDetector;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Il banner e' stato visto: lo segnala la pagina dopo un secondo sullo schermo.
 *
 * Una visualizzazione conta solo se passa tutti i filtri:
 *
 * - l'indirizzo e' firmato dal sito (middleware `signed`), e ogni firma
 *   vale una volta sola;
 * - la campagna e' in corso;
 * - non arriva da un programma che si dichiara tale: quelle si scartano,
 *   ma restano scritte nelle statistiche;
 * - la stessa persona, sulla stessa campagna, conta una volta ogni mezz'ora:
 *   ricaricare la pagina non gonfia i conti di chi compra a visualizzazioni.
 */
class AdvertisementViewController extends Controller
{
    /** Ogni quanto la stessa persona puo' contare di nuovo per la stessa campagna. */
    public const REPEAT_AFTER_MINUTES = 30;

    public function __invoke(Request $request, Advertisement $advertisement, AdServer $ads): Response
    {
        $firstUse = Cache::add('ad-view:'.sha1((string) $request->query('signature')), true, now()->addHours(6));

        if (! $firstUse || ! Advertisement::query()->running()->whereKey($advertisement->id)->exists()) {
            return response()->noContent();
        }

        if (BotDetector::isBot($request->userAgent())) {
            $ads->recordFiltered($advertisement, 'impressions');

            return response()->noContent();
        }

        $key = 'ad-viewer:'.$advertisement->id.':'.AdServer::visitor($request);

        if (Cache::add($key, true, now()->addMinutes(self::REPEAT_AFTER_MINUTES))) {
            $ads->recordView($advertisement);
        }

        return response()->noContent();
    }
}
