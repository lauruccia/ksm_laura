<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTransaction;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanySubscription;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Riepilogo dell'amministrazione.
 *
 * Mostra solo cio' che il ruolo di chi guarda concede: le sezioni che
 * non gli competono non vengono nemmeno interrogate, cosi' la pagina
 * non costa piu' del dovuto a chi ne vede meta'.
 */
class AdminDashboardController extends Controller
{
    /** Mesi mostrati nel grafico dell'andamento. */
    private const MONTHS = 12;

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.dashboard', [
            'cards' => $this->cards($user),
            'orderStates' => $user->can(Permissions::ORDERS_VIEW) ? $this->orderStates() : null,
            'chart' => $this->chart($user),
            'subscriptions' => $user->can(Permissions::SUBSCRIPTIONS_VIEW) ? $this->subscriptions() : null,
            'latestOrders' => $user->can(Permissions::ORDERS_VIEW)
                ? Order::with('company')->latest()->take(8)->get()
                : null,
            'latestCompanies' => $user->can(Permissions::COMPANIES_VIEW)
                ? Company::with('plan')->latest()->take(8)->get()
                : null,
            'pendingSubscriptions' => $user->can(Permissions::SUBSCRIPTIONS_VIEW)
                ? CompanySubscription::with(['company', 'plan'])
                    ->where('status', CompanySubscription::PENDING)
                    ->latest()->take(6)->get()
                : null,
        ]);
    }

    /**
     * Le schede in cima.
     *
     * Ogni scheda porta con se' icona, tinta e, dove ha senso, una
     * seconda riga che spiega il numero grande.
     */
    private function cards(User $user): array
    {
        $cards = [];

        if ($user->can(Permissions::COMPANIES_VIEW)) {
            $total = Company::count();
            $active = Company::where('is_active', true)->count();

            $cards[] = [
                'icon' => 'building',
                'tone' => 'dark',
                'value' => number_format($total, 0, ',', '.'),
                'label' => 'Aziende',
                'note' => $active.' attive',
                'ratio' => $total > 0 ? $active / $total : 0,
                'url' => route('admin.companies.index'),
            ];
        }

        if ($user->can(Permissions::CATALOG_VIEW)) {
            $cards[] = [
                'icon' => 'box',
                'tone' => 'accent',
                'value' => number_format(Product::count(), 0, ',', '.'),
                'label' => 'Prodotti',
                'note' => ProductCategory::count().' categorie prodotto',
                'url' => route('admin.products.index'),
            ];

            $cards[] = [
                'icon' => 'tag',
                'tone' => 'muted',
                'value' => number_format(CompanyCategory::count(), 0, ',', '.'),
                'label' => 'Categorie aziende',
                'url' => route('admin.company_categories.index'),
            ];
        }

        if ($user->can(Permissions::USERS_VIEW)) {
            $byType = User::query()
                ->selectRaw('user_type, count(*) as total')
                ->groupBy('user_type')
                ->pluck('total', 'user_type');
            $count = fn (string $type) => (int) ($byType[$type] ?? 0);

            $cards[] = [
                'icon' => 'users',
                'tone' => 'dark',
                // Gli accessi dei titolari si contano gia' nella scheda Aziende:
                // qui coprirebbero le persone sotto ~98.000 utenze importate.
                'value' => number_format((int) $byType->except('vendor')->sum(), 0, ',', '.'),
                'label' => 'Utenti',
                'note' => collect([
                    $count('admin').' in amministrazione',
                    $count('buyer') ? $count('buyer').($count('buyer') === 1 ? ' cliente' : ' clienti') : null,
                    $count('advertiser') ? $count('advertiser').($count('advertiser') === 1 ? ' inserzionista' : ' inserzionisti') : null,
                ])->filter()->implode(', '),
                'url' => route('admin.users.index'),
            ];
        }

        if ($user->can(Permissions::ORDERS_VIEW)) {
            $cards[] = [
                'icon' => 'cart',
                'tone' => 'accent',
                'value' => number_format(Order::count(), 0, ',', '.'),
                'label' => 'Ordini',
                'note' => Order::where('status', 'pending')->count().' da evadere',
                'url' => route('admin.orders.index'),
            ];
        }

        if ($user->can(Permissions::PAYMENTS_VIEW)) {
            $cards[] = [
                'icon' => 'chart',
                'tone' => 'money',
                'value' => Money::format(Payment::where('status', 'completed')->sum('amount')),
                'label' => 'Incassi delle aziende',
                'note' => 'somma dei pagamenti riusciti',
                'url' => route('admin.payments.index'),
            ];
        }

        if ($user->can(Permissions::SUBSCRIPTIONS_VIEW)) {
            $cards[] = [
                'icon' => 'sparkle',
                'tone' => 'money',
                'value' => Money::format(
                    AdminTransaction::where('status', 'completed')->sum('amount')
                ),
                'label' => 'Quote abbonamento',
                'note' => CompanySubscription::query()->active()->count().' abbonamenti attivi',
                'url' => route('admin.subscriptions.index'),
            ];
        }

        return $cards;
    }

    /** Ordini per stato, con la quota sul totale per la barra. */
    private function orderStates(): array
    {
        $counts = Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $all = max(1, (int) $counts->sum());

        $labels = [
            'pending' => 'In attesa',
            'paid' => 'Pagati',
            'shipped' => 'Spediti',
            'completed' => 'Conclusi',
            'cancelled' => 'Annullati',
        ];

        return collect($labels)
            ->map(fn ($label, $status) => [
                'label' => $label,
                'status' => $status,
                'count' => (int) ($counts[$status] ?? 0),
                'ratio' => ($counts[$status] ?? 0) / $all,
            ])
            ->values()
            ->all();
    }

    /**
     * Andamento degli ultimi dodici mesi.
     *
     * I mesi vuoti restano nel grafico a zero: una linea che salta i
     * mesi senza incassi racconta una crescita che non c'e' stata.
     */
    private function chart(User $user): ?array
    {
        if (! $user->can(Permissions::ORDERS_VIEW) && ! $user->can(Permissions::PAYMENTS_VIEW)) {
            return null;
        }

        $from = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $months = collect(range(0, self::MONTHS - 1))
            ->mapWithKeys(fn ($i) => [
                $from->copy()->addMonths($i)->format('Y-m') => ['orders' => 0, 'revenue' => 0.0],
            ]);

        Order::query()
            ->where('created_at', '>=', $from)
            ->get(['created_at', 'total', 'status'])
            ->each(function ($order) use (&$months) {
                $key = Carbon::parse($order->created_at)->format('Y-m');

                if (! $months->has($key)) {
                    return;
                }

                $row = $months[$key];
                $row['orders']++;

                if (in_array($order->status, ['paid', 'shipped', 'completed'], true)) {
                    $row['revenue'] += (float) $order->total;
                }

                $months[$key] = $row;
            });

        return [
            'labels' => $months->keys()->map(fn ($key) => Carbon::createFromFormat('Y-m', $key)->translatedFormat('M'))->all(),
            'orders' => $months->pluck('orders')->all(),
            'revenue' => $months->pluck('revenue')->all(),
        ];
    }

    /** Abbonamenti attivi divisi per piano, dal piu' ricco al piu' economico. */
    private function subscriptions(): array
    {
        $active = CompanySubscription::query()->active()
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        $all = max(1, (int) $active->sum());

        $plans = Plan::byRank()->get()->map(fn (Plan $plan) => [
            'name' => $plan->name,
            'price' => $plan->price,
            'count' => (int) ($active[$plan->id] ?? 0),
            'ratio' => ($active[$plan->id] ?? 0) / $all,
        ]);

        return [
            'plans' => $plans->all(),
            'active' => (int) $active->sum(),
            'pending' => CompanySubscription::where('status', CompanySubscription::PENDING)->count(),
            'expiring' => CompanySubscription::query()->active()
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now()->addDays(30))
                ->count(),
        ];
    }
}
