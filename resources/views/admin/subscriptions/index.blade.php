@extends('layouts.panel')

@section('title', 'Abbonamenti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Abbonamenti</h1>
    </div>

    @can(\App\Support\Permissions::SUBSCRIPTIONS_MANAGE)
        {{-- Assegnazione diretta: l'azienda non passa da nessun pagamento. --}}
        <form class="ksm-card" style="padding: 18px; margin-bottom: 22px;" method="POST"
              action="{{ route('admin.subscriptions.store') }}">
            @csrf
            <h2 style="font-size: 1.05rem; margin-top: 0;">Attiva un piano a un azienda</h2>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end;">
                {{-- Ricerca e non elenco: le aziende sono quasi centomila. --}}
                <div class="ksm-field" style="margin: 0; min-width: 260px; flex: 1;">
                    <label class="ksm-label" for="company">Azienda</label>
                    <input class="ksm-input" id="company" name="company" list="company-options" autocomplete="off"
                           required value="{{ old('company') }}" placeholder="Nome, oppure #id"
                           data-source="{{ route('admin.subscriptions.companies') }}">
                    <datalist id="company-options"></datalist>
                </div>

                <div class="ksm-field" style="margin: 0; min-width: 200px;">
                    <label class="ksm-label" for="plan_id">Piano</label>
                    <select class="ksm-select" id="plan_id" name="plan_id" required>
                        <option value="">Scegli</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ksm-field" style="margin: 0; min-width: 220px; flex: 1;">
                    <label class="ksm-label" for="notes">Nota</label>
                    <input class="ksm-input" id="notes" name="notes" value="{{ old('notes') }}"
                           placeholder="Perche, per chi lo legge fra sei mesi">
                </div>

                <button class="ksm-btn ksm-btn--primary" type="submit">Attiva</button>
            </div>

            <small class="ksm-muted">
                Il piano parte subito e l'azienda si accende. Non viene registrato nessun incasso.
            </small>

            @error('company_id')<span class="ksm-error">{{ $message }}</span>@enderror
            @error('plan_id')<span class="ksm-error">{{ $message }}</span>@enderror
        </form>

        {{-- Rinnovo in blocco: stessa scadenza spostata avanti di un periodo. --}}
        <form class="ksm-card" style="padding: 18px; margin-bottom: 22px;" method="POST"
              action="{{ route('admin.subscriptions.extend') }}"
              onsubmit="return confirm('Allungare di un periodo tutti gli abbonamenti scelti? Non viene registrato nessun incasso.');">
            @csrf
            <h2 style="font-size: 1.05rem; margin-top: 0;">Rinnovo in blocco</h2>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end;">
                <div class="ksm-field" style="margin: 0; min-width: 280px;">
                    <label class="ksm-label" for="extend_plan_id">Piano</label>
                    <select class="ksm-select" id="extend_plan_id" name="extend_plan_id" required>
                        <option value="">Scegli</option>
                        @foreach ($plans->reject->isLifetime() as $plan)
                            <option value="{{ $plan->id }}" @selected(old('extend_plan_id') == $plan->id)>
                                {{ $plan->name }} · {{ number_format($expiring[$plan->id] ?? 0, 0, ',', '.') }}
                                in scadenza entro {{ $expiringDays }} giorni
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ksm-field" style="margin: 0; min-width: 200px;">
                    <label class="ksm-label" for="extend_ending_before">Solo con scadenza entro il</label>
                    <input class="ksm-input" id="extend_ending_before" name="extend_ending_before" type="date"
                           value="{{ old('extend_ending_before') }}">
                </div>

                <button class="ksm-btn ksm-btn--primary" type="submit">Rinnova</button>
            </div>

            <small class="ksm-muted">
                Ogni abbonamento attivo del piano si allunga di un periodo a partire dalla sua scadenza.
                Lascia vuota la data per rinnovarli tutti. Chi ha i promemoria spenti continua a non riceverli.
            </small>

            @error('extend_plan_id')<span class="ksm-error">{{ $message }}</span>@enderror
            @error('extend_ending_before')<span class="ksm-error">{{ $message }}</span>@enderror
        </form>
    @endcan

    <form method="GET" class="ksm-filters">
        <input class="ksm-input" name="cerca" value="{{ request('cerca') }}" placeholder="Cerca azienda">

        <select class="ksm-select" name="stato" aria-label="Stato">
            <option value="">Tutti gli stati</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected(request('stato') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select class="ksm-select" name="piano" aria-label="Piano">
            <option value="">Tutti i piani</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}" @selected(request('piano') == $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>

        <button class="ksm-btn ksm-btn--ghost" type="submit">Filtra</button>
    </form>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                <th>Azienda</th>
                <th>Piano</th>
                <th>Quota</th>
                <th>Metodo</th>
                <th>Stato</th>
                <th>Periodo</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($subscriptions as $subscription)
                <tr>
                    <td>{{ $subscription->company?->name ?? '—' }}</td>
                    <td>{{ $subscription->plan?->name ?? '—' }}</td>
                    <td>{{ \App\Support\Money::format($subscription->price) }}</td>
                    <td>
                        {{ $subscription->payment_method
                            ? \App\Payments\Subscriptions\SubscriptionGatewayManager::label($subscription->payment_method)
                            : '—' }}
                    </td>
                    <td>
                        <span class="ksm-badge @if ($subscription->status !== 'active') ksm-badge--muted @endif">
                            {{ $subscription->statusLabel() }}
                        </span>
                    </td>
                    <td style="white-space: nowrap;">
                        {{ $subscription->starts_at?->format('d/m/Y') ?? '—' }}
                        →
                        {{ $subscription->ends_at?->format('d/m/Y')
                            ?? ($subscription->status === 'active' ? 'senza scadenza' : '—') }}
                        @if ($subscription->ends_at && ! $subscription->send_reminders)
                            <br><small class="ksm-muted">promemoria spenti</small>
                        @endif
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                        @can(\App\Support\Permissions::SUBSCRIPTIONS_MANAGE)
                            @if ($subscription->status !== 'active')
                                <form method="POST" action="{{ route('admin.subscriptions.confirm', $subscription) }}"
                                      style="display: inline;"
                                      onsubmit="return confirm('Confermare l incasso e attivare il piano?');">
                                    @csrf @method('PATCH')
                                    <button class="ksm-btn ksm-btn--ghost" type="submit">Conferma incasso</button>
                                </form>
                            @else
                                @if ($subscription->ends_at)
                                    <form method="POST" action="{{ route('admin.subscriptions.reminders', $subscription) }}"
                                          style="display: inline;">
                                        @csrf @method('PATCH')
                                        <button class="ksm-btn ksm-btn--ghost" type="submit">
                                            {{ $subscription->send_reminders ? 'Spegni promemoria' : 'Accendi promemoria' }}
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}"
                                      style="display: inline;"
                                      onsubmit="return confirm('Chiudere questo abbonamento?');">
                                    @csrf @method('PATCH')
                                    <button class="ksm-btn ksm-btn--ghost" type="submit">Chiudi</button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="ksm-muted">Nessun abbonamento.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $subscriptions->links() }}</div>

    @can(\App\Support\Permissions::SUBSCRIPTIONS_MANAGE)
        <script>
            // Propone le aziende mentre si scrive. L'etichetta scelta finisce
            // con "#id", ed e' quello che il server rilegge: senza script basta
            // scrivere l'id a mano.
            (() => {
                const input = document.getElementById('company');
                const options = document.getElementById('company-options');
                let timer;

                input.addEventListener('input', () => {
                    clearTimeout(timer);
                    const term = input.value.trim();

                    if (/#\d+$/.test(term) || term.length < 2) {
                        return;
                    }

                    timer = setTimeout(async () => {
                        const url = `${input.dataset.source}?q=${encodeURIComponent(term)}`;
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });

                        if (!response.ok) {
                            return;
                        }

                        const companies = await response.json();
                        options.replaceChildren(...companies.map(({ label }) => {
                            const option = document.createElement('option');
                            option.value = label;
                            return option;
                        }));
                    }, 250);
                });
            })();
        </script>
    @endcan
@endsection
