<?php

use App\Support\PlanCapabilities as C;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allinea i dati gia' presenti alle regole nuove.
     *
     * Prima che i piani avessero le voci, un'azienda attiva compariva in
     * directory e poteva vendere. Chi ha gia' un piano si tiene quel
     * comportamento: nessuna scheda sparisce per via dell'aggiornamento.
     */
    public function up(): void
    {
        $tutto = [
            C::DIRECTORY, C::CONTACT_CARD, C::LOGO, C::BANNER, C::FEATURED,
            C::SHOWCASE, C::GALLERY, C::DESCRIPTION, C::SHOP, C::REVIEWS,
        ];

        DB::table('plans')->whereNull('capabilities')->update([
            'capabilities' => json_encode($tutto),
        ]);

        // Un periodo aperto per chi il piano ce l'ha gia': senza, la
        // pagina del piano direbbe che non e' abbonato a niente.
        $companies = DB::table('companies')
            ->whereNotNull('plan_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('company_subscriptions')
                ->whereColumn('company_subscriptions.company_id', 'companies.id'))
            ->get(['id', 'plan_id']);

        foreach ($companies as $company) {
            $plan = DB::table('plans')->where('id', $company->plan_id)->first(['price', 'duration_days']);

            DB::table('company_subscriptions')->insert([
                'company_id' => $company->id,
                'plan_id' => $company->plan_id,
                'status' => 'active',
                'price' => $plan->price ?? 0,
                'currency' => config('ksm.currency', 'EUR'),
                'starts_at' => now(),
                'ends_at' => now()->addDays((int) ($plan->duration_days ?? 365)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Nessun ritorno: i dati riempiti qui non si distinguono da
        // quelli inseriti a mano dopo l'aggiornamento.
    }
};
