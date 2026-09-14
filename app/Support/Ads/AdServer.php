<?php

namespace App\Support\Ads;

use App\Models\Advertisement;
use App\Models\AdvertisementStat;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sceglie il banner da mostrare e ne tiene i conti.
 *
 * Fra le campagne in corso per una posizione tiene quelle mirate sul
 * contesto e ne pesca una a caso, cosi' chi divide una posizione divide
 * anche le visualizzazioni. Sui siti propri delle aziende non mostra
 * niente: nessuno vuole la pubblicita' di altri sul proprio sito.
 */
class AdServer
{
    public function pick(string $placement, AdContext $context): ?Advertisement
    {
        if (app(TenantContext::class)->isCompanySite()) {
            return null;
        }

        return Advertisement::query()
            ->running()
            ->whereNotNull('img')
            ->inLocation($placement)
            ->get()
            ->filter(fn (Advertisement $campaign) => $campaign->matches($context))
            ->shuffle()
            ->first();
    }

    public function recordView(Advertisement $campaign): void
    {
        $this->record($campaign, 'impressions');
    }

    public function recordClick(Advertisement $campaign): void
    {
        $this->record($campaign, 'clicks');
    }

    /**
     * Visualizzazione o clic di un programma automatico.
     *
     * Non tocca i totali della campagna, che decidono limiti e conto:
     * resta solo scritto nella riga del giorno.
     */
    public function recordFiltered(Advertisement $campaign, string $kind): void
    {
        $column = $kind === 'clicks' ? 'filtered_clicks' : 'filtered_impressions';

        AdvertisementStat::upsert(
            [[
                'advertisement_id' => $campaign->id,
                'day' => now()->toDateString(),
                $column => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['advertisement_id', 'day'],
            [$column => DB::raw("$column + 1"), 'updated_at' => now()]
        );
    }

    /** Chi guarda, senza sapere chi e': indirizzo e browser, ridotti a un'impronta. */
    public static function visitor(Request $request): string
    {
        return sha1($request->ip().'|'.$request->userAgent());
    }

    /** Totale sulla campagna, per i limiti, e riga del giorno, per le statistiche. */
    private function record(Advertisement $campaign, string $column): void
    {
        DB::transaction(function () use ($campaign, $column) {
            Advertisement::whereKey($campaign->id)->increment($column);

            AdvertisementStat::upsert(
                [[
                    'advertisement_id' => $campaign->id,
                    'day' => now()->toDateString(),
                    'impressions' => $column === 'impressions' ? 1 : 0,
                    'clicks' => $column === 'clicks' ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['advertisement_id', 'day'],
                [$column => DB::raw("$column + 1"), 'updated_at' => now()]
            );
        });
    }
}
