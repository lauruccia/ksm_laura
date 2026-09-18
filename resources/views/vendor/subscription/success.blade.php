@extends('layouts.app')

@section('title', 'Piano attivo')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 620px;">
            <div class="ksm-card" style="padding: 28px;">
                <h1 style="font-size: 1.6rem;">Piano {{ $subscription->plan->name }} attivo</h1>

                <ul class="ksm-meta">
                    <li>Dal {{ $subscription->starts_at?->format('d/m/Y') }}</li>
                    <li>{{ $subscription->ends_at ? 'Fino al '.$subscription->ends_at->format('d/m/Y') : 'Senza scadenza' }}</li>
                    <li>{{ \App\Support\Money::format($subscription->price) }}</li>
                </ul>

                <p style="margin-top: 16px;">La tua azienda e ora visibile nella directory.</p>

                <div style="margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <a class="ksm-btn ksm-btn--primary" href="{{ route('vendor.dashboard') }}">Vai all'area azienda</a>
                    @if ($subscription->company->hasPage())
                        <a class="ksm-btn ksm-btn--ghost" href="{{ route('companies.show', $subscription->company->slug) }}">
                            Vedi la vetrina
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
