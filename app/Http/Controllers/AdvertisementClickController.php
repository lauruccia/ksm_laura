<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Support\Ads\AdServer;
use App\Support\Ads\BotDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Clic su un banner: si conta e si va al sito dell'inserzionista.
 *
 * Un clic per persona all'ora: ricaricare o insistere non fa pagare di
 * piu' chi compra a clic. I clic dei programmi automatici si scartano e
 * restano solo nelle statistiche. Su una campagna ferma si va al sito lo
 * stesso, ma il clic non si conta.
 */
class AdvertisementClickController extends Controller
{
    public function __invoke(Request $request, Advertisement $advertisement, AdServer $ads): RedirectResponse
    {
        if (Advertisement::query()->running()->whereKey($advertisement->id)->exists()) {
            if (BotDetector::isBot($request->userAgent())) {
                $ads->recordFiltered($advertisement, 'clicks');
            } elseif (Cache::add('ad-click:'.$advertisement->id.':'.AdServer::visitor($request), true, now()->addHour())) {
                $ads->recordClick($advertisement);
            }
        }

        return $advertisement->link
            ? redirect()->away($advertisement->link)
            : redirect()->route('home');
    }
}
