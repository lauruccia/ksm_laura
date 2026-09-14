@extends('layouts.app')

@section('title', 'Pagamento non completato')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 620px;">
            <div class="ksm-card" style="padding: 28px;">
                <h1 style="font-size: 1.6rem;">Pagamento non completato</h1>

                <p>
                    Il piano {{ $subscription->plan->name }} non risulta pagato, quindi non è stato attivato.
                    Non è stato addebitato nulla.
                </p>

                <div style="margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <a class="ksm-btn ksm-btn--primary" href="{{ route('subscription.payment', $subscription) }}">
                        Riprova
                    </a>
                    <a class="ksm-btn ksm-btn--ghost" href="{{ route('subscription.index') }}">Scegli un altro piano</a>
                </div>
            </div>
        </div>
    </section>
@endsection
