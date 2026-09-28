@extends('layouts.panel')

@section('title', 'Abbonamenti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    {{-- Titolo, numero e filtri su una riga sola, come negli altri elenchi. --}}
    <div class="ksm-listhead">
        <h1>Abbonamenti <span class="ksm-listhead__count">{{ number_format($subscriptions->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Cerca azienda" aria-label="Cerca azienda">
            </span>
            <select class="ksm-select" name="stato" aria-label="Stato" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('stato') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="ksm-select" name="piano" aria-label="Piano" onchange="this.form.submit()">
                <option value="">Tutti i piani</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(request('piano') == $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'stato', 'piano']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.subscriptions.index') }}">Azzera</a>
            @endif
        </form>
    </div>

    @can(\App\Support\Permissions::SUBSCRIPTIONS_MANAGE)
        <div class="ksm-panel-grid ksm-panel-grid--even ksm-subtools">
            {{-- Assegnazione diretta: l'azienda non passa da nessun pagamento. --}}
            <form class="ksm-box" method="POST" action="{{ route('admin.subscriptions.store') }}">
                @csrf
                <div class="ksm-box__head"><h2>Attiva un piano a un'azienda</h2></div>

                <div class="ksm-toolform">
                    {{-- Ricerca e non elenco: le aziende sono quasi centomila. --}}
                    <div class="ksm-field">
                        <label class="ksm-label" for="company">Azienda</label>
                        <input class="ksm-input" id="company" name="company" list="company-options" autocomplete="off"
                               required value="{{ old('company') }}" placeholder="Nome, oppure #id"
                               data-source="{{ route('admin.subscriptions.companies') }}">
                        <datalist id="company-options"></datalist>
                    </div>

                    <div class="ksm-field">
                        <label class="ksm-label" for="plan_id">Piano</label>
                        <select class="ksm-select" id="plan_id" name="plan_id" required>
                            <option value="">Scegli</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ksm-field">
                        <label class="ksm-label" for="notes">Nota</label>
                        <input class="ksm-input" id="notes" name="notes" value="{{ old('notes') }}"
                               placeholder="Perché, per chi lo legge fra sei mesi">
                    </div>

                    <button class="ksm-btn ksm-btn--primary" type="submit">Attiva</button>
                </div>

                @error('company_id')<span class="ksm-error">{{ $message }}</span>@enderror
                @error('plan_id')<span class="ksm-error">{{ $message }}</span>@enderror

                <p class="ksm-toolform__hint">
                    Il piano parte subito e l'azienda si accende. Non viene registrato nessun incasso.
                </p>
            </form>

            {{-- Rinnovo in blocco: stessa scadenza spostata avanti di un periodo. --}}
            <form class="ksm-box" method="POST" action="{{ route('admin.subscriptions.extend') }}"
                  onsubmit="return confirm('Allungare di un periodo tutti gli abbonamenti scelti? Non viene registrato nessun incasso.');">
                @csrf
                <div class="ksm-box__head"><h2>Rinnovo in blocco</h2></div>

                <div class="ksm-toolform">
                    <div class="ksm-field">
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

                    <div class="ksm-field">
                        <label class="ksm-label" for="extend_ending_before">Solo con scadenza entro il</label>
                        <input class="ksm-input" id="extend_ending_before" name="extend_ending_before" type="date"
                               value="{{ old('extend_ending_before') }}">
                    </div>

                    <p class="ksm-toolform__hint">
                        Ogni abbonamento attivo del piano si allunga di un periodo a partire dalla sua scadenza.
                        Lascia vuota la data per rinnovarli tutti. Chi ha i promemoria spenti continua a non riceverli.
                    </p>

                    <button class="ksm-btn ksm-btn--primary" type="submit">Rinnova</button>
                </div>

                @error('extend_plan_id')<span class="ksm-error">{{ $message }}</span>@enderror
                @error('extend_ending_before')<span class="ksm-error">{{ $message }}</span>@enderror
            </form>
        </div>
    @endcan

    <div class="ksm-table-wrap">
        <table class="ksm-table ksm-table--subscriptions">
            <thead>
            <tr>
                <th>Azienda</th>
                <th>Piano</th>
                <th class="is-num">Quota</th>
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
                    <td class="is-num">{{ \App\Support\Money::format($subscription->price) }}</td>
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
                    <td>
                        {{ $subscription->starts_at?->format('d/m/Y') ?? '—' }}
                        →
                        {{ $subscription->ends_at?->format('d/m/Y')
                            ?? ($subscription->status === 'active' ? 'senza scadenza' : '—') }}
                        @if ($subscription->ends_at && ! $subscription->send_reminders)
                            <br><small class="ksm-muted">promemoria spenti</small>
                        @endif
                    </td>
                    <td>
                        <div class="ksm-rowactions">
                            @can(\App\Support\Permissions::SUBSCRIPTIONS_MANAGE)
                                @if ($subscription->status !== 'active')
                                    <form method="POST" action="{{ route('admin.subscriptions.confirm', $subscription) }}"
                                          onsubmit="return confirm('Confermare l\'incasso e attivare il piano?');">
                                        @csrf @method('PATCH')
                                        <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Conferma incasso</button>
                                    </form>
                                @else
                                    @if ($subscription->ends_at)
                                        <form method="POST" action="{{ route('admin.subscriptions.reminders', $subscription) }}">
                                            @csrf @method('PATCH')
                                            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">
                                                {{ $subscription->send_reminders ? 'Spegni promemoria' : 'Accendi promemoria' }}
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}"
                                          onsubmit="return confirm('Chiudere questo abbonamento?');">
                                        @csrf @method('PATCH')
                                        <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Chiudi</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="ksm-muted">Nessun abbonamento.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="ksm-pager">{{ $subscriptions->links() }}</div>

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
