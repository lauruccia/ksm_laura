@extends('layouts.app')

@section('title', 'Il tuo piano')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container">
            <div class="ksm-section-head">
                <div>
                    <h2>{{ __('site.step_plan_title') }}</h2>
                    <p>{{ __('site.step_plan_text') }}</p>
                </div>
            </div>

            @if ($current)
                <div class="ksm-card" style="padding: 24px; margin-bottom: 28px;">
                    <h3>Piano in corso: {{ $current->plan->name }}</h3>
                    <ul class="ksm-meta">
                        <li>Attivo dal {{ $current->starts_at?->format('d/m/Y') }}</li>
                        <li>{{ $current->ends_at ? 'Scade il '.$current->ends_at->format('d/m/Y') : 'Non scade' }}</li>
                        <li>{{ \App\Support\Money::format($current->price) }}</li>
                    </ul>
                    <p class="ksm-muted">Scegli un altro piano qui sotto per cambiare o rinnovare.</p>
                </div>
            @elseif ($pending)
                <div class="ksm-card" style="padding: 24px; margin-bottom: 28px;">
                    <h3>{{ $pending->plan->name }} · in attesa di pagamento</h3>
                    <p class="ksm-muted">
                        L'azienda resta nascosta finche non risulta incassata la quota di
                        {{ \App\Support\Money::format($pending->price) }}.
                    </p>
                    <a class="ksm-btn ksm-btn--primary" href="{{ route('subscription.payment', $pending) }}">
                        Riprendi il pagamento
                    </a>
                </div>
            @endif

            @if ($plans->isEmpty())
                <p class="ksm-muted">{{ __('site.no_results') }}</p>
            @else
                <div class="ksm-grid ksm-grid--4">
                    @foreach ($plans as $plan)
                        @php($quote = $quotes[$plan->id])
                        <x-plan-card :plan="$plan" :highlight="session('piano_scelto') === $plan->slug">
                            @if ($current)
                                <p class="ksm-muted" style="margin: 0 0 10px;">
                                    <strong>
                                        {{ $quote->isFree() ? 'Niente da pagare' : 'Ora paghi '.\App\Support\Money::format($quote->amount) }}
                                    </strong><br>
                                    {{ $quote->explain() }}
                                    @if ($quote->credit > 0)
                                        <br>Residuo scalato: {{ \App\Support\Money::format($quote->credit) }}.
                                    @endif
                                </p>
                            @endif

                            <form method="POST" action="{{ route('subscription.store') }}">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">
                                    {{ $current?->plan_id === $plan->id ? 'Rinnova' : 'Scegli questo piano' }}
                                </button>
                            </form>
                        </x-plan-card>
                    @endforeach
                </div>

                @unless ($current || $pending)
                    <p style="margin-top: 18px;">
                        <a class="ksm-btn ksm-btn--ghost" href="{{ route('home') }}">Decido dopo</a>
                        <span class="ksm-muted">
                            Puoi tornare qui quando vuoi. Finche non scegli un piano, l'azienda non compare nella directory.
                        </span>
                    </p>
                @endunless
            @endif

            @if ($history->isNotEmpty())
                <div class="ksm-section-head" style="margin-top: 40px;"><h2>Storico</h2></div>

                <div class="ksm-table-wrap">
                    <table class="ksm-table">
                        <thead>
                            <tr>
                                <th>Piano</th>
                                <th>Stato</th>
                                <th>Periodo</th>
                                <th>Quota</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $row)
                                <tr>
                                    <td>{{ $row->plan->name }}</td>
                                    <td>{{ $row->statusLabel() }}</td>
                                    <td>
                                        {{ $row->starts_at?->format('d/m/Y') ?? '—' }}
                                        →
                                        {{ $row->ends_at?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td>{{ \App\Support\Money::format($row->price) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
