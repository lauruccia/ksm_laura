<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * La pagina si e' chiusa: dice per quanti secondi e' stata davanti agli occhi.
 *
 * Arriva da analytics.js con sendBeacon. Si aggiorna solo una riga che
 * esiste, indicata dalla sigla casuale che il sito ha scritto nella pagina,
 * e il valore puo' solo crescere: la pagina manda il totale ogni volta che
 * l'utente la lascia, e ritornarci non azzera niente.
 */
class AnalyticsDurationController extends Controller
{
    /** Oltre un'ora la scheda e' rimasta aperta e dimenticata: non e' attenzione. */
    private const MAX_SECONDS = 3600;

    public function __invoke(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'pv' => ['required', 'string', 'size:26'],
            't' => ['required', 'integer', 'min:1'],
        ]);

        // Niente pagina di errore: chi chiama e' uno script della pagina, non una persona.
        if ($validator->fails()) {
            return response()->noContent(422);
        }

        $data = $validator->validated();
        $seconds = min((int) $data['t'], self::MAX_SECONDS);

        PageView::query()
            ->where('uid', $data['pv'])
            ->where('duration', '<', $seconds)
            ->update(['duration' => $seconds]);

        return response()->noContent();
    }
}
