@extends('layouts.app')

@section('title', 'Pagamento del piano')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 620px;">
            <div class="ksm-section-head">
                <div>
                    <h2>Pagamento</h2>
                    <p>{{ $subscription->plan->name }} · {{ \App\Support\Money::format($subscription->price) }}</p>
                </div>
            </div>

            @if ($methods === [])
                <div class="ksm-card" style="padding: 24px;">
                    <p>Non c e ancora un modo per incassare la quota.</p>
                    <p class="ksm-muted">
                        Scrivi a {{ \App\Models\AdminSetting::current()->support_email ?? \App\Models\AdminSetting::current()->website_email }}
                        e attiviamo il pagamento.
                    </p>
                </div>
            @else
                <form class="ksm-card" style="padding: 24px;" method="POST"
                      action="{{ route('subscription.pay', $subscription) }}">
                    @csrf

                    @foreach ($methods as $method)
                        <label class="ksm-field" style="display: flex; gap: 10px; align-items: flex-start;">
                            <input type="radio" name="method" value="{{ $method }}" @checked($loop->first)>
                            <span>
                                <strong>{{ \App\Payments\Subscriptions\SubscriptionGatewayManager::label($method) }}</strong>
                                @if ($method === 'bank_transfer')
                                    <br>
                                    <small class="ksm-muted">
                                        Ti mostriamo l'IBAN. Il piano parte quando il bonifico risulta arrivato.
                                    </small>
                                @endif
                            </span>
                        </label>
                    @endforeach

                    @error('method')<span class="ksm-error">{{ $message }}</span>@enderror

                    <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Prosegui</button>
                </form>
            @endif

            <p style="margin-top: 18px;">
                <a class="ksm-btn ksm-btn--ghost" href="{{ route('subscription.index') }}">Cambia piano</a>
            </p>
        </div>
    </section>
@endsection
