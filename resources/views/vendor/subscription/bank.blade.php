@extends('layouts.app')

@section('title', 'Bonifico')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 620px;">
            <div class="ksm-section-head">
                <div>
                    <h2>Paga con bonifico</h2>
                    <p>{{ $subscription->plan->name }} · {{ \App\Support\Money::format($subscription->price) }}</p>
                </div>
            </div>

            <div class="ksm-card" style="padding: 24px;">
                <ul class="ksm-meta">
                    @if ($settings->bank_holder)<li><strong>Intestatario</strong> {{ $settings->bank_holder }}</li>@endif
                    @if ($settings->bank_iban)<li><strong>IBAN</strong> {{ $settings->bank_iban }}</li>@endif
                    @if ($settings->bank_bic)<li><strong>BIC</strong> {{ $settings->bank_bic }}</li>@endif
                    <li><strong>Importo</strong> {{ \App\Support\Money::format($subscription->price) }}</li>
                    <li><strong>Causale</strong> Piano {{ $subscription->plan->name }} · abbonamento {{ $subscription->id }}</li>
                </ul>

                @if ($settings->bank_instructions)
                    <p style="margin-top: 16px;">{{ $settings->bank_instructions }}</p>
                @endif

                <p class="ksm-muted" style="margin-top: 16px;">
                    Riporta la causale esatta: serve a riconoscere il tuo versamento. Il piano parte
                    quando il bonifico risulta arrivato, di solito in due o tre giorni lavorativi.
                </p>
            </div>

            <p style="margin-top: 18px;">
                <a class="ksm-btn ksm-btn--ghost" href="{{ route('subscription.index') }}">Torna al piano</a>
            </p>
        </div>
    </section>
@endsection
