<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Plan;
use App\Payments\Subscriptions\SubscriptionActivator;
use App\Payments\Subscriptions\SubscriptionExtension;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Abbonamenti delle aziende ai piani.
 *
 * Qui l'amministratore fa le cose che nessun gestore puo' fare per lui:
 * confermare un bonifico arrivato sul conto, assegnare un piano senza
 * pagamento, rinnovare in blocco le aziende inserite da Gruppo Kosmos.
 */
class AdminSubscriptionController extends Controller
{
    /** Finestra dei conteggi "in scadenza" accanto al rinnovo in blocco. */
    private const EXPIRING_DAYS = 90;

    public function __construct(
        private readonly SubscriptionActivator $activator,
        private readonly SubscriptionExtension $extension,
    ) {}

    public function index(Request $request): View
    {
        $subscriptions = CompanySubscription::query()
            ->with(['company:id,name', 'plan:id,name'])
            ->when($request->string('stato')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('piano'), fn ($q, $id) => $q->where('plan_id', $id))
            // Sottoquery invece di whereHas: la ricerca sulle aziende gira una volta sola.
            ->when($request->string('cerca')->toString(), fn ($q, $term) => $q->whereIn(
                'company_id',
                Company::where('name', 'like', "%$term%")->select('id')
            ))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'statuses' => CompanySubscription::STATUSES,
            'plans' => Plan::byRank()->get(),
            'expiring' => CompanySubscription::query()
                ->active()
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now()->addDays(self::EXPIRING_DAYS))
                ->selectRaw('plan_id, count(*) as total')
                ->groupBy('plan_id')
                ->pluck('total', 'plan_id'),
            'expiringDays' => self::EXPIRING_DAYS,
        ]);
    }

    /**
     * Aziende per la casella di ricerca del modulo di attivazione.
     *
     * Poche righe alla volta: con centomila aziende l'elenco completo
     * pesava diversi megabyte a ogni apertura della pagina.
     */
    public function companies(Request $request): JsonResponse
    {
        $term = trim(ltrim($request->string('q')->toString(), '#'));

        if ($term === '' || (mb_strlen($term) < 2 && ! ctype_digit($term))) {
            return response()->json([]);
        }

        $companies = Company::query()
            ->when(
                ctype_digit($term),
                fn ($q) => $q->whereKey((int) $term),
                fn ($q) => $q->where('name', 'like', "%$term%")->orderBy('name')
            )
            ->limit(15)
            ->get(['id', 'name', 'city']);

        return response()->json($companies->map(fn (Company $company) => [
            'id' => $company->id,
            'label' => self::label($company),
        ]));
    }

    /** Assegna un piano a un'azienda qualsiasi, subito attivo e senza incasso. */
    public function store(Request $request): RedirectResponse
    {
        // La casella di ricerca manda l'etichetta: l'azienda e' l'id in fondo.
        if (! $request->filled('company_id')
            && preg_match('/#(\d+)\s*$/', (string) $request->input('company'), $match)) {
            $request->merge(['company_id' => $match[1]]);
        }

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'company_id.required' => __('Scegli l\'azienda fra quelle proposte dalla ricerca.'),
        ]);

        $company = Company::findOrFail($data['company_id']);

        $this->activator->assign($company, Plan::findOrFail($data['plan_id']), $data['notes'] ?? null);

        return back()->with('success', __('Piano attivato per :azienda.', ['azienda' => $company->name]));
    }

    /** Allunga di un periodo gli abbonamenti attivi di un piano che scade. */
    public function extend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'extend_plan_id' => ['required', Rule::exists('plans', 'id')->whereNotNull('duration_days')],
            'extend_ending_before' => ['nullable', 'date'],
        ], [
            'extend_plan_id.exists' => __('Si rinnovano in blocco solo i piani che scadono.'),
        ]);

        $plan = Plan::findOrFail($data['extend_plan_id']);
        $endingBefore = filled($data['extend_ending_before'] ?? null)
            ? Carbon::parse($data['extend_ending_before'])->endOfDay()
            : null;

        $count = $this->extension->extend($plan, $endingBefore);

        // Nessun movimento lo racconta: nel log resta chi l'ha fatto e su cosa.
        Log::info('Rinnovo in blocco degli abbonamenti', [
            'admin' => $request->user()->id,
            'plan' => $plan->id,
            'ending_before' => $endingBefore?->toDateString(),
            'count' => $count,
        ]);

        return back()->with('success', __(':count abbonamenti :piano allungati di :giorni giorni.', [
            'count' => number_format($count, 0, ',', '.'),
            'piano' => $plan->name,
            'giorni' => $plan->duration_days,
        ]));
    }

    /** Bonifico visto sul conto: il piano entra in vigore. */
    public function confirm(CompanySubscription $subscription): RedirectResponse
    {
        if ($subscription->status === CompanySubscription::ACTIVE) {
            return back()->with('error', __('Questo abbonamento e gia attivo.'));
        }

        $transaction = $subscription->transactions()->latest('id')->first();

        if ($transaction) {
            $this->activator->markPaid($subscription, $transaction, $transaction->transaction_id, [
                'confermato_in_amministrazione' => true,
                'confermato_il' => now()->toDateTimeString(),
            ]);
        } else {
            $this->activator->activate($subscription);
        }

        return back()->with('success', __('Abbonamento attivato.'));
    }

    /** Chiude il periodo subito: l'azienda si spegne, i suoi dati restano. */
    public function cancel(CompanySubscription $subscription): RedirectResponse
    {
        $this->activator->cancel($subscription);

        return back()->with('success', __('Abbonamento chiuso.'));
    }

    /** Accende o spegne i promemoria di rinnovo di un abbonamento. */
    public function reminders(CompanySubscription $subscription): RedirectResponse
    {
        $subscription->update(['send_reminders' => ! $subscription->send_reminders]);

        return back()->with('success', $subscription->send_reminders
            ? __('Promemoria di rinnovo accesi.')
            : __('Promemoria di rinnovo spenti.'));
    }

    /** "Nome · Citta' · #id": l'id in fondo e' quello che il modulo rilegge. */
    private static function label(Company $company): string
    {
        return implode(' · ', array_filter([$company->name, $company->city, "#$company->id"]));
    }
}
